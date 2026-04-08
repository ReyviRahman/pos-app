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

    public $paid_amount = '';
    public string $payment_method = 'cash';

    public float $total = 0;
    public float $change = 0;

    public function checkoutAlpine($orderId, $paymentMethod, $paidAmount)
    {
        $this->selectedOrder = Order::with('items.product')->find($orderId);
        if (!$this->selectedOrder) {
            session()->flash('error', 'Silakan pilih pesanan terlebih dahulu.');
            return;
        }

        $this->payment_method = $paymentMethod;
        $this->paid_amount = $paidAmount;

        // Rebuild cart context securely from backend
        $this->cart = [];
        foreach ($this->selectedOrder->items as $item) {
            $this->cart[$item->product_id] = [
                'id' => $item->product_id,
                'name' => $item->product->name ?? 'Produk Dihapus',
                'price' => $item->price,
                'qty' => $item->quantity,
            ];
        }

        // recalculate security metrics
        $this->total = $this->selectedOrder->total_price;
        $this->change = $this->payment_method === 'cash' ? max(0, $this->paid_amount - $this->total) : 0;

        if ($this->payment_method === 'cash') {
            if ($this->paid_amount < $this->total) {
                $this->addError('paid_amount', 'Uang pelanggan tidak cukup.');
                return;
            }
        }

        $this->processDirectPayment();
    }

    protected function processDirectPayment()
    {
        DB::transaction(function () {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

            $transaction = Transaction::create([
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->payment_method === 'cash' ? (float) $this->paid_amount : $this->total,
                'change_amount' => $this->payment_method === 'cash' ? $this->change : 0,
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

        $this->reset(['cart', 'paid_amount', 'payment_method', 'selectedOrder']);
        session()->flash('success', 'Transaksi berhasil!');

        $newOrders = Order::with('items.product')->where('status', 'unpaid')->orderBy('created_at', 'asc')->get()->toArray();
        $this->dispatch('transaction-completed', orders: $newOrders);
    }

    protected function deductProductIngredients(array $item, string $invoiceNumber)
    {
        $product = Product::with('ingredients')->find($item['id']);
        if ($product) {
            foreach ($product->ingredients as $ingredient) {
                $totalIngredientUsed = $item['qty'] * $ingredient->pivot->quantity_used;

                InventoryMovement::create([
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
            'unpaidOrders' => Order::with('items.product')->where('status', 'unpaid')->orderBy('created_at', 'asc')->get(),
        ];
    }
};
?>

<div x-data="paymentGateway(@js($unpaidOrders))"
    @transaction-completed.window="cancelSelection(); orders = $event.detail.orders">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Point of Sale') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            <div class="flex flex-col lg:flex-row gap-6">

                <div class="w-full lg:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-700">Daftar Pesanan (Belum Dibayar)</h3>
                        <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-1 rounded-full"
                            x-text="orders.length + ' Pesanan'">
                            {{ count($unpaidOrders) }} Pesanan
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <template x-for="order in orders" :key="order.id">
                            <div @click="selectOrder(order.id)"
                                :class="selectedOrderId === order.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'"
                                class="border-2 rounded-xl p-5 cursor-pointer hover:border-blue-400 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="font-bold text-gray-800 text-lg leading-tight"
                                        x-text="order.customer_name"></div>
                                    <span class="bg-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                        Meja <span x-text="order.table_number || '-'"></span>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 mb-2" x-text="order.order_number"></div>
                                <div class="flex justify-between items-end mt-4 pt-3 border-t border-gray-200">
                                    <div class="text-xs text-gray-400"
                                        x-text="new Date(order.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})">
                                    </div>
                                    <div class="text-blue-600 font-extrabold" x-text="formatRupiah(order.total_price)">
                                    </div>
                                </div>
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
                                <span class="text-sm text-gray-400 mt-1">Pesanan yang dibuat dari halaman order akan
                                    muncul
                                    di sini.</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    class="w-full lg:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-0 flex flex-col h-[calc(100vh-12rem)] min-h-[500px]">

                    <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-700">Detail Pembayaran</h3>
                        <template x-if="selectedOrder">
                            <button @click="cancelSelection()"
                                class="text-red-500 hover:text-red-700 text-sm font-semibold flex items-center gap-1 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Batal Pilih
                            </button>
                        </template>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        @error('cart') <span class="text-red-500 text-sm block mb-2">{{ $message }}</span> @enderror
                        @error('paid_amount') <span class="text-red-500 text-sm block mb-2">{{ $message }}</span>
                        @enderror

                        <template x-for="item in cart" :key="item.id">
                            <div class="flex justify-between items-center border-b pb-3 mb-3 last:border-0 last:pb-0">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 text-sm" x-text="item.name"></h4>
                                    <div class="text-gray-500 text-xs" x-text="formatRupiah(item.price)"></div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-sm text-gray-600" x-text="'x' + item.qty"></div>
                                    <div class="text-sm font-bold text-gray-800 w-24 text-right"
                                        x-text="formatRupiah(item.price * item.qty)"></div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!selectedOrder">
                            <div class="text-center text-gray-400 py-10 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122">
                                    </path>
                                </svg>
                                <span>Pilih pesanan di sebelah kiri</span>
                            </div>
                        </template>
                    </div>

                    <!-- Pindah ke block pembayaran bawah -->
                    <div :class="selectedOrder ? 'opacity-100' : 'opacity-50 pointer-events-none'"
                        class="p-4 bg-gray-50 border-t transition-opacity">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-gray-600 font-semibold text-lg">Total Transaksi</span>
                            <span class="text-2xl font-bold text-gray-800" x-text="formatRupiah(total)"></span>
                        </div>

                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Metode Pembayaran</label>
                                <select x-model="paymentMethod"
                                    class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="cash">Tunai / Cash</option>
                                    <option value="qris_edc">QRIS EDC</option>
                                </select>
                            </div>

                            <template x-if="paymentMethod === 'cash'">
                                <div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Uang Diterima
                                            (Rp)</label>
                                        <input type="number" x-model="paidAmount" placeholder="0"
                                            class="w-full border-gray-300 rounded-md text-lg font-bold text-right focus:ring-blue-500 focus:border-blue-500">
                                    </div>

                                    <div
                                        class="flex justify-between items-center pt-2 border-t border-dashed border-gray-300 mt-2">
                                        <span class="text-gray-500 font-medium text-sm">Kembalian</span>
                                        <span class="font-bold" :class="change > 0 ? 'text-green-600' : 'text-gray-800'"
                                            x-text="formatRupiah(change)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button @click="checkout"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-md transition flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            <span x-text="paymentMethod === 'cash' ? 'Bayar Pesanan' : 'Selesaikan Pembayaran'"></span>
                        </button>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- AlpineJS Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentGateway', (initialOrders) => ({
                orders: initialOrders,
                selectedOrderId: null,
                paymentMethod: 'cash',
                paidAmount: '',

                get selectedOrder() {
                    return this.orders.find(o => o.id === this.selectedOrderId);
                },
                get cart() {
                    if (!this.selectedOrder) return [];
                    return this.selectedOrder.items.map(item => ({
                        id: item.product_id,
                        name: item.product ? item.product.name : 'Produk Dihapus',
                        price: item.price,
                        qty: item.quantity
                    }));
                },
                get total() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                },
                get change() {
                    let paid = parseFloat(this.paidAmount) || 0;
                    return paid > this.total ? paid - this.total : 0;
                },
                selectOrder(id) {
                    this.selectedOrderId = id;
                    this.paidAmount = '';
                    this.paymentMethod = 'cash';
                },
                cancelSelection() {
                    this.selectedOrderId = null;
                },
                checkout() {
                    if (!this.selectedOrderId) return;
                    if (this.paymentMethod === 'cash' && (parseFloat(this.paidAmount) || 0) < this.total) {
                        alert('Gagal: Nominal uang tunai yang diterima kurang dari total tagihan pembayaran.');
                        return;
                    }

                    this.$wire.checkoutAlpine(this.selectedOrderId, this.paymentMethod, this.paidAmount || 0);
                },
                formatRupiah(number) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
                }
            }));
        });
    </script>
</div>