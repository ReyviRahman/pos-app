<?php

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\MidtransService;
use App\Services\XenditService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public ?int $selectedOrderId = null;
    public ?Order $selectedOrder = null;
    public array $cart = [];

    public $paid_amount = '';
    public string $payment_method = 'cash';

    public ?array $pendingPayment = null;
    public ?array $midtransPayment = null;

    public function selectOrder($orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = Order::with('items.product')->find($orderId);

        $this->cart = [];
        if ($this->selectedOrder) {
            foreach ($this->selectedOrder->items as $item) {
                $this->cart[$item->product_id] = [
                    'id' => $item->product_id,
                    'name' => $item->product->name ?? 'Produk Dihapus',
                    'price' => $item->price,
                    'qty' => $item->quantity,
                ];
            }
        }
        $this->paid_amount = '';
    }

    public function cancelOrderSelection()
    {
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->cart = [];
        $this->paid_amount = '';
    }

    public function getTotalProperty()
    {
        $total = 0;
        foreach ($this->cart as $item) {
            $total += $item['price'] * $item['qty'];
        }

        return $total;
    }

    public function getChangeProperty()
    {
        $paid = (float) $this->paid_amount;

        return $paid >= $this->total ? $paid - $this->total : 0;
    }

    public function checkout()
    {
        if (!$this->selectedOrder) {
            session()->flash('error', 'Silakan pilih pesanan terlebih dahulu.');
            return;
        }

        if ($this->payment_method === 'cash') {
            $this->validate([
                'cart' => 'required|array|min:1',
                'paid_amount' => 'required|numeric|min:' . $this->total,
            ], [
                'cart.required' => 'Keranjang belanja masih kosong.',
                'paid_amount.min' => 'Uang pelanggan tidak cukup.',
            ]);

            $this->processDirectPayment();
        } elseif (in_array($this->payment_method, ['gopay', 'qris_edc'])) {
            $this->validate([
                'cart' => 'required|array|min:1',
            ], [
                'cart.required' => 'Keranjang belanja masih kosong.',
            ]);

            $this->processDirectPayment();
        } elseif ($this->payment_method === 'midtrans_qris') {
            $this->validate([
                'cart' => 'required|array|min:1',
            ], [
                'cart.required' => 'Keranjang belanja masih kosong.',
            ]);

            $this->processSnapPayment();

        } else {
            $this->validate([
                'cart' => 'required|array|min:1',
            ], [
                'cart.required' => 'Keranjang belanja masih kosong.',
            ]);

            $this->processXenditPayment();
        }
    }

    protected function processDirectPayment()
    {
        DB::transaction(function () {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

            $transaction = Transaction::create([
                'username_cashier' => auth()->user()->username ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->payment_method === 'cash' ? (float) $this->paid_amount : $this->total,
                'change_amount' => $this->payment_method === 'cash' ? $this->change : 0,
                'payment_method' => $this->payment_method,
                'status' => 'completed',
                'xendit_payment_status' => 'SUCCEEDED',
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

        $this->reset(['cart', 'paid_amount', 'payment_method', 'selectedOrderId', 'selectedOrder']);
        session()->flash('success', 'Transaksi berhasil!');
    }

    protected function processXenditPayment()
    {
        $xenditService = app(XenditService::class);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

        $channelCode = $this->payment_method === 'qris' ? 'QRIS' : 'BCA';
        $channelProperties = [];

        if ($this->payment_method === 'qris') {
            $channelProperties = [
                'qr_string_type' => 'DYNAMIC',
            ];
        } else {
            $channelProperties = [
                'amount' => $this->total,
            ];
        }

        $paymentData = [
            'reference_id' => $invoiceNumber,
            'amount' => $this->total,
            'channel_code' => $channelCode,
            'channel_properties' => $channelProperties,
            'description' => 'Payment for order ' . $invoiceNumber,
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'payment_method' => $this->payment_method,
            ],
        ];

        $result = $xenditService->createPayment($paymentData);

        if (!$result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran: ' . $result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            $transaction = Transaction::create([
                'username_cashier' => auth()->user()->username ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->total,
                'change_amount' => 0,
                'payment_method' => $this->payment_method,
                'status' => 'pending',
                'xendit_payment_request_id' => $result['payment_request_id'],
                'xendit_payment_url' => $result['payment_url'],
                'xendit_payment_status' => $result['status'],
                'xendit_channel_code' => $result['channel_code'],
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
            }

            if ($this->selectedOrder) {
                $this->selectedOrder->update(['status' => 'paid']);
            }
        });

        $this->pendingPayment = [
            'invoice_number' => $invoiceNumber,
            'payment_url' => $result['payment_url'],
            'payment_request_id' => $result['payment_request_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method', 'selectedOrderId', 'selectedOrder']);
    }

    protected function processMidtransPayment()
    {
        $midtransService = app(MidtransService::class);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

        $result = $midtransService->createBriPayment($this->total, $invoiceNumber);

        if (!$result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran BRI: ' . $result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            Transaction::create([
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->total,
                'change_amount' => 0,
                'payment_method' => 'midtrans_bri',
                'status' => 'pending',
                'xendit_payment_status' => 'PENDING',
                'midtrans_order_id' => $result['order_id'],
                'midtrans_qr_string' => $result['redirect_url'],
                'xendit_metadata' => ['cart' => json_encode($this->cart)],
            ]);

            if ($this->selectedOrder) {
                $this->selectedOrder->update(['status' => 'paid']);
            }
        });

        $this->midtransPayment = [
            'invoice_number' => $invoiceNumber,
            'redirect_url' => $result['redirect_url'],
            'va_number' => $result['va_number'],
            'bank' => $result['bank'],
            'order_id' => $result['order_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method', 'selectedOrderId', 'selectedOrder']);
    }

    protected function processSnapPayment()
    {
        $midtransService = app(MidtransService::class);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());

        $result = $midtransService->createSnapPayment($this->total, $invoiceNumber);

        if (!$result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran: ' . $result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            Transaction::create([
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->selectedOrder->customer_name,
                'table_number' => $this->selectedOrder->table_number,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->total,
                'change_amount' => 0,
                'payment_method' => 'midtrans_qris',
                'status' => 'pending',
                'xendit_payment_status' => 'PENDING',
                'midtrans_order_id' => $result['order_id'],
                'midtrans_redirect_url' => $result['redirect_url'],
                'midtrans_snap_token' => $result['token'],
                'xendit_metadata' => ['cart' => json_encode($this->cart)],
            ]);

            if ($this->selectedOrder) {
                $this->selectedOrder->update(['status' => 'paid']);
            }
        });

        $this->midtransPayment = [
            'invoice_number' => $invoiceNumber,
            'redirect_url' => $result['redirect_url'],
            'snap_token' => $result['token'],
            'order_id' => $result['order_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method', 'selectedOrderId', 'selectedOrder']);
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

    public function cancelPendingPayment()
    {
        $this->pendingPayment = null;
    }

    public function cancelMidtransPayment()
    {
        $this->midtransPayment = null;
    }

    public function checkMidtransStatus($orderId)
    {
        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();
        if (!$transaction) {
            return 'failed';
        }

        if ($transaction->status === 'completed') {
            return 'completed';
        }

        if ($transaction->status === 'canceled') {
            return 'failed';
        }

        return 'pending';
    }

    public function with(): array
    {
        return [
            'unpaidOrders' => Order::with('items')->where('status', 'unpaid')->orderBy('created_at', 'asc')->get(),
            'pendingPayment' => $this->pendingPayment,
            'midtransPayment' => $this->midtransPayment,
        ];
    }
};
?>

<div>
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

            @if($pendingPayment)
                <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-2">Menunggu Pembayaran</h3>
                            <p class="text-gray-600">Invoice: <span
                                    class="font-mono">{{ $pendingPayment['invoice_number'] }}</span></p>
                            <p class="text-gray-600">Total: <span class="font-bold text-blue-600">Rp
                                    {{ number_format($pendingPayment['amount'], 0, ',', '.') }}</span></p>
                            <p class="text-sm text-gray-500 mt-2">Silakan selesaikan pembayaran melalui link berikut:</p>
                        </div>
                        <button wire:click="cancelPendingPayment" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <a href="{{ $pendingPayment['payment_url'] }}" target="_blank"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Buka Halaman Pembayaran
                        </a>
                        <button wire:click="cancelPendingPayment"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-md transition">
                            Tutup
                        </button>
                    </div>
                </div>
            @endif

            @if($midtransPayment)
                <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6"
                    x-data="{ polling: true, toast: null }" x-init="
                            setTimeout(() => {
                                snap.pay('{{ $midtransPayment['snap_token'] ?? $midtransPayment['redirect_url'] }}', {
                                    onSuccess: function(result) {
                                        toast = { type: 'success', message: 'Pembayaran berhasil!' };
                                        setTimeout(() => window.location.reload(), 2000);
                                    },
                                    onPending: function(result) {
                                        toast = { type: 'pending', message: 'Pembayaran masih menunggu konfirmasi.' };
                                    },
                                    onError: function(result) {
                                        toast = { type: 'error', message: 'Pembayaran gagal. Silakan coba lagi.' };
                                    },
                                    onClose: function() {
                                    }
                                });
                            }, 500);

                            const checkStatus = setInterval(() => {
                                @this.call('checkMidtransStatus', '{{ $midtransPayment['order_id'] }}').then(result => {
                                    if (result === 'completed') {
                                        clearInterval(checkStatus);
                                        polling = false;
                                        toast = { type: 'success', message: 'Pembayaran berhasil dikonfirmasi!' };
                                        setTimeout(() => window.location.reload(), 2000);
                                    } else if (result === 'failed') {
                                        clearInterval(checkStatus);
                                        polling = false;
                                        toast = { type: 'error', message: 'Pembayaran gagal atau kadaluarsa.' };
                                    }
                                });
                            }, 3000);
                        ">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-2">Selesaikan Pembayaran</h3>
                            <p class="text-gray-600">Invoice: <span
                                    class="font-mono">{{ $midtransPayment['invoice_number'] }}</span></p>
                            <p class="text-gray-600">Total: <span class="font-bold text-blue-600">Rp
                                    {{ number_format($midtransPayment['amount'], 0, ',', '.') }}</span></p>
                            <p class="text-sm text-gray-500 mt-2">Scan QRIS pada popup yang muncul:</p>
                        </div>
                        <button wire:click="cancelMidtransPayment" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-4 flex flex-col items-center">
                        @if(!empty($midtransPayment['snap_token']))
                            <button @click="snap.pay('{{ $midtransPayment['snap_token'] }}', {
                                                onSuccess: function(result) {
                                                    toast = { type: 'success', message: 'Pembayaran berhasil!' };
                                                    setTimeout(() => window.location.reload(), 2000);
                                                },
                                                onPending: function(result) {
                                                    toast = { type: 'pending', message: 'Pembayaran masih menunggu konfirmasi.' };
                                                },
                                                onError: function(result) {
                                                    toast = { type: 'error', message: 'Pembayaran gagal. Silakan coba lagi.' };
                                                },
                                                onClose: function() {
                                                }
                                            })"
                                class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-md">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Buka Pembayaran Ulang
                            </button>
                        @elseif(!empty($midtransPayment['redirect_url']))
                            <a href="{{ $midtransPayment['redirect_url'] }}" target="_blank"
                                class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-md">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Buka Halaman Pembayaran
                            </a>
                        @endif
                        <p class="text-sm text-gray-500 mt-3" x-show="polling">Menunggu pembayaran... <span
                                class="inline-block animate-spin">&#x27F3;</span></p>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button wire:click="cancelMidtransPayment"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-md transition">
                            Tutup
                        </button>
                    </div>

                    <div x-show="toast" x-cloak x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-2" class="fixed top-4 right-4 z-50 max-w-sm">
                        <div class="p-4 rounded-lg shadow-lg flex items-center gap-3" :class="{
                                        'bg-green-500 text-white': toast?.type === 'success',
                                        'bg-red-500 text-white': toast?.type === 'error',
                                        'bg-yellow-500 text-white': toast?.type === 'pending'
                                     }">
                            <template x-if="toast?.type === 'success'">
                                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </template>
                            <template x-if="toast?.type === 'error'">
                                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </template>
                            <template x-if="toast?.type === 'pending'">
                                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </template>
                            <p class="font-medium text-sm" x-text="toast?.message"></p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex flex-col lg:flex-row gap-6">

                <div class="w-full lg:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-700">Daftar Pesanan (Belum Dibayar)</h3>
                        <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-1 rounded-full">
                            {{ count($unpaidOrders) }} Pesanan
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($unpaidOrders as $order)
                            <div wire:key="order-{{ $order->id }}"
                                wire:click="selectOrder({{ $order->id }})"
                                class="border-2 {{ $selectedOrderId === $order->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50' }} rounded-xl p-5 cursor-pointer hover:border-blue-400 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="font-bold text-gray-800 text-lg leading-tight">
                                        {{ $order->customer_name }}
                                    </div>
                                    <span class="bg-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                        Meja {{ $order->table_number ?? '-' }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 mb-2">
                                    {{ $order->order_number }}
                                </div>
                                <div class="flex justify-between items-end mt-4 pt-3 border-t border-gray-200">
                                    <div class="text-xs text-gray-400">
                                        {{ $order->created_at->format('H:i') }}
                                    </div>
                                    <div class="text-blue-600 font-extrabold">
                                        Rp {{ number_format($order->total_price, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        @empty
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
                        @endforelse
                    </div>
                </div>

                <div
                    class="w-full lg:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-0 flex flex-col h-[calc(100vh-12rem)] min-h-[500px]">

                    <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-700">Detail Pembayaran</h3>
                        @if($selectedOrder)
                            <button wire:click="cancelOrderSelection"
                                class="text-red-500 hover:text-red-700 text-sm font-semibold flex items-center gap-1 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Batal Pilih
                            </button>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        @error('cart') <span class="text-red-500 text-sm block mb-2">{{ $message }}</span> @enderror

                        @forelse($cart as $id => $item)
                            <div wire:key="payment-cart-{{ $id }}" class="flex justify-between items-center border-b pb-3 last:border-0 last:pb-0">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 text-sm">{{ $item['name'] }}</h4>
                                    <div class="text-gray-500 text-xs">Rp {{ number_format($item['price'], 0, ',', '.') }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-sm text-gray-600">
                                        x{{ $item['qty'] }}
                                    </div>
                                    <div class="text-sm font-bold text-gray-800 w-24 text-right">
                                        Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-400 py-10 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122">
                                    </path>
                                </svg>
                                <span>Pilih pesanan di sebelah kiri</span>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pindah ke block pembayaran bawah -->
                    <div
                        class="p-4 bg-gray-50 border-t {{ $selectedOrder ? 'opacity-100' : 'opacity-50 pointer-events-none' }}">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-gray-600 font-semibold text-lg">Total Transaksi</span>
                            <span class="text-2xl font-bold text-gray-800">Rp
                                {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>

                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Metode Pembayaran</label>
                                <select wire:model="payment_method"
                                    class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="cash">Tunai / Cash</option>
                                    <option value="qris_edc">QRIS EDC</option>
                                    {{-- <option value="gopay">Gopay Speaker</option> --}}
                                    {{-- <option value="qris">QRIS</option> --}}
                                    {{-- <option value="midtrans_qris">Midtrans QRIS</option>
                                    <option value="transfer">Transfer Bank</option> --}}
                                </select>
                            </div>

                            @if ($this->payment_method === 'cash')
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Uang Diterima (Rp)</label>
                                    <input type="number" wire:model.live="paid_amount" placeholder="0"
                                        class="w-full border-gray-300 rounded-md text-lg font-bold text-right focus:ring-blue-500 focus:border-blue-500">
                                    @error('paid_amount') <span
                                    class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div
                                    class="flex justify-between items-center pt-2 border-t border-dashed border-gray-300 mt-2">
                                    <span class="text-gray-500 font-medium text-sm">Kembalian</span>
                                    <span class="font-bold {{ $this->change > 0 ? 'text-green-600' : 'text-gray-800' }}">
                                        Rp {{ number_format($this->change, 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <button wire:click="checkout"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-md transition flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            {{ $this->payment_method === 'cash' ? 'Bayar Pesanan' : 'Buat Pembayaran' }}
                        </button>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>