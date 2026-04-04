<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\MidtransService;
use App\Services\XenditService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public array $cart = [];

    public $paid_amount = '';

    public string $payment_method = 'cash';

    public ?array $pendingPayment = null;

    public ?array $midtransPayment = null;

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty']++;
        } else {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'qty' => 1,
            ];
        }
    }

    public function increaseQty($productId)
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty']++;
        }
    }

    public function decreaseQty($productId)
    {
        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['qty'] > 1) {
                $this->cart[$productId]['qty']--;
            } else {
                $this->removeFromCart($productId);
            }
        }
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
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
        if ($this->payment_method === 'cash') {
            $this->validate([
                'cart' => 'required|array|min:1',
                'paid_amount' => 'required|numeric|min:'.$this->total,
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
            $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(uniqid());

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'total_amount' => $this->total,
                'paid_amount' => $this->payment_method === 'cash' ? $this->paid_amount : $this->total,
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
        });

        $this->reset(['cart', 'paid_amount', 'payment_method']);
        session()->flash('success', 'Transaksi berhasil! Stok bahan baku telah terpotong otomatis.');
    }

    protected function processXenditPayment()
    {
        $xenditService = app(XenditService::class);
        $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(uniqid());

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
            'description' => 'Payment for order '.$invoiceNumber,
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'payment_method' => $this->payment_method,
            ],
        ];

        $result = $xenditService->createPayment($paymentData);

        if (! $result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran: '.$result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            $transaction = Transaction::create([
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
        });

        $this->pendingPayment = [
            'invoice_number' => $invoiceNumber,
            'payment_url' => $result['payment_url'],
            'payment_request_id' => $result['payment_request_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method']);
    }

    protected function processMidtransPayment()
    {
        $midtransService = app(MidtransService::class);
        $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(uniqid());

        $result = $midtransService->createBriPayment($this->total, $invoiceNumber);

        if (! $result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran BRI: '.$result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            Transaction::create([
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
        });

        $this->midtransPayment = [
            'invoice_number' => $invoiceNumber,
            'redirect_url' => $result['redirect_url'],
            'va_number' => $result['va_number'],
            'bank' => $result['bank'],
            'order_id' => $result['order_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method']);
    }

    protected function processSnapPayment()
    {
        $midtransService = app(MidtransService::class);
        $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(uniqid());

        $result = $midtransService->createSnapPayment($this->total, $invoiceNumber);

        if (! $result['success']) {
            session()->flash('error', 'Gagal memproses pembayaran: '.$result['message']);

            return;
        }

        DB::transaction(function () use ($invoiceNumber, $result) {
            Transaction::create([
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
        });

        $this->midtransPayment = [
            'invoice_number' => $invoiceNumber,
            'redirect_url' => $result['redirect_url'],
            'snap_token' => $result['token'],
            'order_id' => $result['order_id'],
            'amount' => $this->total,
        ];

        $this->reset(['cart', 'paid_amount', 'payment_method']);
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
        if (! $transaction) {
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
            'products' => Product::orderBy('name')->get(),
            'pendingPayment' => $this->pendingPayment,
            'midtransPayment' => $this->midtransPayment,
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Point of Sale (Kasir (MArsaaaa))') }}
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
                            <p class="text-gray-600">Invoice: <span class="font-mono">{{ $pendingPayment['invoice_number'] }}</span></p>
                            <p class="text-gray-600">Total: <span class="font-bold text-blue-600">Rp {{ number_format($pendingPayment['amount'], 0, ',', '.') }}</span></p>
                            <p class="text-sm text-gray-500 mt-2">Silakan selesaikan pembayaran melalui link berikut:</p>
                        </div>
                        <button wire:click="cancelPendingPayment" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <a href="{{ $pendingPayment['payment_url'] }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            Buka Halaman Pembayaran
                        </a>
                        <button wire:click="cancelPendingPayment" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-md transition">
                            Tutup
                        </button>
                    </div>
                </div>
            @endif

            @if($midtransPayment)
                <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="{ polling: true }" x-init="
                    setTimeout(() => {
                        snap.pay('{{ $midtransPayment['snap_token'] ?? $midtransPayment['redirect_url'] }}', {
                            onSuccess: function(result) {
                                window.location.reload();
                            },
                            onPending: function(result) {
                            },
                            onError: function(result) {
                            },
                            onClose: function() {
                            }
                        });
                    }, 500);

                    const checkStatus = setInterval(() => {
                        @this.call('checkMidtransStatus', '{{ $midtransPayment['order_id'] }}').then(result => {
                            if (result === 'completed') {
                                clearInterval(checkStatus);
                                window.location.reload();
                            } else if (result === 'failed') {
                                clearInterval(checkStatus);
                                polling = false;
                            }
                        });
                    }, 3000);
                ">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-2">Selesaikan Pembayaran</h3>
                            <p class="text-gray-600">Invoice: <span class="font-mono">{{ $midtransPayment['invoice_number'] }}</span></p>
                            <p class="text-gray-600">Total: <span class="font-bold text-blue-600">Rp {{ number_format($midtransPayment['amount'], 0, ',', '.') }}</span></p>
                            <p class="text-sm text-gray-500 mt-2">Pilih metode pembayaran (QRIS, GoPay, Transfer Bank, dll) pada popup berikut:</p>
                        </div>
                        <button wire:click="cancelMidtransPayment" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="mt-4 flex flex-col items-center">
                        @if(!empty($midtransPayment['snap_token']))
                            <button @click="snap.pay('{{ $midtransPayment['snap_token'] }}', {
                                onSuccess: function(result) {
                                    window.location.reload();
                                },
                                onPending: function(result) {
                                },
                                onError: function(result) {
                                },
                                onClose: function() {
                                }
                            })" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-md">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                Buka Pembayaran
                            </button>
                        @elseif(!empty($midtransPayment['redirect_url']))
                            <a href="{{ $midtransPayment['redirect_url'] }}" target="_blank" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-md">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                Buka Halaman Pembayaran
                            </a>
                        @endif
                        <p class="text-sm text-gray-500 mt-3" x-show="polling">Menunggu pembayaran... <span class="inline-block animate-spin">&#x27F3;</span></p>
                        <p class="text-sm text-red-500 mt-3" x-show="!polling">Pembayaran gagal atau kadaluarsa.</p>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button wire:click="cancelMidtransPayment" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-md transition">
                            Tutup
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-col lg:flex-row gap-6">
                
                <div class="w-full lg:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Pilih Menu</h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @forelse($products as $product)
                            <div wire:click="addToCart({{ $product->id }})" 
                                 class="border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 hover:shadow-md transition bg-gray-50 flex flex-col justify-between h-32">
                                <div class="font-semibold text-gray-800 text-sm leading-tight mb-2">
                                    {{ $product->name }}
                                </div>
                                <div class="text-blue-600 font-bold text-sm">
                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center text-gray-500 py-8">
                                Belum ada menu produk. Silakan tambahkan di menu Produk.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="w-full lg:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-0 flex flex-col h-[calc(100vh-12rem)] min-h-[500px]">
                    
                    <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-700">Order Summary</h3>
                        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">
                            {{ count($cart) }} Item
                        </span>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        @error('cart') <span class="text-red-500 text-sm block mb-2">{{ $message }}</span> @enderror
                        
                        @forelse($cart as $id => $item)
                            <div class="flex justify-between items-center border-b pb-3 last:border-0 last:pb-0">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 text-sm">{{ $item['name'] }}</h4>
                                    <div class="text-gray-500 text-xs">Rp {{ number_format($item['price'], 0, ',', '.') }}</div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center border rounded-md overflow-hidden">
                                        <button wire:click="decreaseQty({{ $id }})" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 transition">-</button>
                                        <span class="px-3 py-1 text-sm font-medium w-8 text-center">{{ $item['qty'] }}</span>
                                        <button wire:click="increaseQty({{ $id }})" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 transition">+</button>
                                    </div>
                                    <div class="text-sm font-bold text-gray-800 w-20 text-right">
                                        Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-400 py-10 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Pilih menu di sebelah kiri</span>
                            </div>
                        @endforelse
                    </div>

                    <div class="p-4 bg-gray-50 border-t">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-gray-600 font-semibold text-lg">Total</span>
                            <span class="text-2xl font-bold text-gray-800">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>

                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Metode Pembayaran</label>
                                <select wire:model="payment_method" class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="cash">Tunai / Cash</option>
                                    <option value="gopay">Gopay Speaker</option>
                                    <option value="qris">QRIS</option>
                                    <option value="qris_edc">QRIS EDC</option>
                                    <option value="midtrans_qris">Midtrans QRIS</option>
                                    <option value="transfer">Transfer Bank</option>
                                </select>
                            </div>

                            @if ($this->payment_method === 'cash')
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Uang Diterima (Rp)</label>
                                    <input type="number" wire:model.live="paid_amount" placeholder="0" 
                                           class="w-full border-gray-300 rounded-md text-lg font-bold text-right focus:ring-blue-500 focus:border-blue-500">
                                    @error('paid_amount') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                                </div>
                                
                                <div class="flex justify-between items-center pt-2 border-t border-dashed border-gray-300 mt-2">
                                    <span class="text-gray-500 font-medium text-sm">Kembalian</span>
                                    <span class="font-bold {{ $this->change > 0 ? 'text-green-600' : 'text-gray-800' }}">
                                        Rp {{ number_format($this->change, 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <button wire:click="checkout" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-md transition flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            {{ $this->payment_method === 'cash' ? 'Bayar Pesanan' : 'Buat Pembayaran' }}
                        </button>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>
