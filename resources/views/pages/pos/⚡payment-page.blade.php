<?php

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public ?int $selectedOrderId = null;
    public ?Order $selectedOrder = null;
    public array $cart = [];

    public function mount()
    {
        if (auth()->user()->role !== 'kasir') {
            abort(403, 'Akses Ditolak: Hanya Kasir yang berhak mengakses halaman pembayaran.');
        }
    }

    public string $payment_method = 'qris_edc';
    public float $total = 0;

    public function fetchOrderDetail($orderId)
    {
        $order = auth()->user()->branch->orders()
            ->with('items.product')
            ->where('status', 'unpaid')
            ->find($orderId);

        if (!$order)
            return null;

        return $order->toArray();
    }

    public function checkoutAlpine($orderId, $paymentMethod)
    {
        // PERBAIKAN KEAMANAN: Kunci by branch & cegah double-payment
        $this->selectedOrder = auth()->user()->branch->orders()
            ->with('items.product')
            ->where('status', 'unpaid') // Memastikan order belum dibayar sebelumnya
            ->find($orderId);

        if (!$this->selectedOrder) {
            session()->flash('error', 'Pesanan tidak ditemukan, bukan milik cabang ini, atau sudah dibayar.');
            return;
        }

        $this->payment_method = $paymentMethod;

        // Rebuild cart context securely from backend, excluding rejected items
        $this->cart = [];
        foreach ($this->selectedOrder->items as $item) {
            if ($item->kitchen_status === 'rejected')
                continue;

            $this->cart[$item->product_id] = [
                'id' => $item->product_id,
                'name' => $item->product->name ?? 'Produk Dihapus',
                'price' => $item->price,
                'qty' => $item->quantity,
            ];
        }

        // Recalculate total excluding rejected items
        $this->total = collect($this->cart)->sum(fn($item) => $item['price'] * $item['qty']);

        $this->processDirectPayment();
    }

    protected function processDirectPayment()
    {
        DB::transaction(function () {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

            $transaction = auth()->user()->branch->transactions()->create([
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->total, // Otomatis pas
                'change_amount' => 0, // Tidak ada kembalian
                'payment_method' => $this->payment_method,
                'status' => 'completed',
            ]);

            foreach ($this->cart as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['qty'],
                ]);

                $this->deductProductIngredients($item, $invoiceNumber);
            }

            if ($this->selectedOrder) {
                $this->selectedOrder->update(['status' => 'paid']);
            }
        });

        $this->reset(['cart', 'payment_method', 'selectedOrder']);
        session()->flash('success', 'Transaksi berhasil!');

        $newOrders = auth()->user()->branch->orders()->with('items.product')->where('status', 'unpaid')->orderBy('created_at', 'asc')->get()->toArray();
        $this->dispatch('transaction-completed', orders: $newOrders);
    }

    protected function deductProductIngredients(array $item, string $invoiceNumber)
    {
        $product = Product::with('ingredients')->find($item['id']);
        if ($product) {
            foreach ($product->ingredients as $ingredient) {
                $totalIngredientUsed = $item['qty'] * $ingredient->pivot->quantity_used;

                auth()->user()->branch->inventoryMovements()->create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'out',
                    'quantity' => $totalIngredientUsed,
                    'price_per_unit' => $ingredient->price_per_unit,
                    'reference_id' => $invoiceNumber,
                ]);

                $ingredient->decrement('current_stock', $totalIngredientUsed);
            }
        }
    }

    public function with(): array
    {
        return [
            'unpaidOrders' => auth()->user()->branch->orders()->with('items.product')->where('status', 'unpaid')->orderBy('created_at', 'asc')->get(),
        ];
    }
};
?>

<div x-data="paymentGateway(@js($unpaidOrders))"
    @transaction-completed.window="cancelSelection(); orders = $event.detail.orders">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Point of Sale - Kasir') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session()->has('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-lg font-bold text-gray-700">Daftar Pesanan (Belum Dibayar)</h3>
                    <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-1 rounded-full"
                        x-text="orders.length + ' Pesanan'">
                        {{ count($unpaidOrders) }} Pesanan
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-for="order in orders" :key="order.id">
                        <div @click="selectOrder(order.id)"
                            class="border-2 border-gray-200 bg-gray-50 rounded-xl p-4 cursor-pointer hover:border-blue-400 hover:shadow-md transition">
                            <div class="flex justify-between items-center mb-2">
                                <div class="font-bold text-gray-800 text-base leading-tight truncate"
                                    x-text="order.customer_name"></div>
                                <span
                                    class="bg-gray-200 text-gray-700 text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0 ml-2">
                                    Meja <span x-text="order.table_number || '-'"></span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-400">
                                <span x-text="order.order_number"></span>
                                <span
                                    x-text="new Date(order.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})"></span>
                            </div>
                            <div class="mt-2 text-[10px] text-gray-400" x-text="orderItemCount(order) + ' item'"></div>
                        </div>
                    </template>

                    <template x-if="orders.length === 0">
                        <div class="col-span-full text-center text-gray-500 py-12 flex flex-col items-center">
                            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                                </path>
                            </svg>
                            <span class="text-lg font-medium">Belum ada pesanan masuk.</span>
                            <span class="text-sm text-gray-400 mt-1">Pesanan yang dibuat dari halaman order akan muncul
                                di sini.</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div x-show="showModal" x-cloak style="display: none;"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        x-transition.opacity>
        <div @click.outside="cancelSelection()"
            class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl flex flex-col max-h-[85vh]"
            x-show="showModal" x-transition.scale.95>
            <!-- Header -->
            <div class="p-4 bg-gray-50 border-b flex justify-between items-center flex-shrink-0">
                <div>
                    <h4 class="font-bold text-gray-800 text-lg">Detail Pembayaran</h4>
                    <p class="text-xs text-gray-500" x-show="selectedOrder">
                        <span x-text="selectedOrder?.customer_name"></span> •
                        Meja <span x-text="selectedOrder?.table_number || '-'"></span>
                    </p>
                </div>
                <button @click="cancelSelection()" class="text-gray-400 hover:text-rose-500 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Item List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <template x-for="item in cart" :key="item.id">
                    <div
                        class="flex justify-between items-center border-b border-gray-100 pb-2.5 last:border-0 last:pb-0">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 text-sm" x-text="item.name"></h4>
                            <div class="text-gray-400 text-xs" x-text="formatRupiah(item.price) + ' × ' + item.qty">
                            </div>
                        </div>
                        <div class="text-sm font-bold text-gray-800 ml-3" x-text="formatRupiah(item.price * item.qty)">
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="p-4 bg-gray-50 border-t flex-shrink-0 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 font-semibold text-lg">Total</span>
                    <span class="text-2xl font-bold text-gray-800" x-text="formatRupiah(total)"></span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Metode Pembayaran</label>
                    <select x-model="paymentMethod"
                        class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="qris_edc">QRIS EDC</option>
                    </select>
                </div>
                <button @click="checkout()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-md transition flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    Selesaikan Pembayaran
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentGateway', (initialOrders) => ({
                orders: initialOrders,
                selectedOrderId: null,
                showModal: false,
                modalOrder: null,
                paymentMethod: 'qris_edc',

                get selectedOrder() {
                    return this.modalOrder;
                },
                get cart() {
                    if (!this.selectedOrder) return [];
                    return this.selectedOrder.items
                        .filter(item => item.kitchen_status !== 'rejected')
                        .map(item => ({
                            id: item.product_id,
                            name: item.product ? item.product.name : 'Produk Dihapus',
                            price: item.price,
                            qty: item.quantity
                        }));
                },
                get total() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                },
                async selectOrder(id) {
                    this.selectedOrderId = id;
                    this.paymentMethod = 'qris_edc';
                    // Fetch fresh data from DB via Livewire
                    const freshOrder = await this.$wire.fetchOrderDetail(id);
                    if (freshOrder) {
                        this.modalOrder = freshOrder;
                        this.showModal = true;
                    }
                },
                cancelSelection() {
                    this.showModal = false;
                    this.selectedOrderId = null;
                    this.modalOrder = null;
                },
                checkout() {
                    if (!this.selectedOrderId) return;
                    this.$wire.checkoutAlpine(this.selectedOrderId, this.paymentMethod);
                },
                orderItemCount(order) {
                    return order.items.filter(item => item.kitchen_status !== 'rejected').length;
                },
                formatRupiah(number) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
                }
            }));
        });
    </script>
</div>