<?php

use App\Models\Karyawan;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public array $cart = [];

    public string $nikInput = '';

    public ?Karyawan $currentKaryawan = null;

    public string $karyawanError = '';

    public string $karyawanPaymentMethod = 'qris_edc';

    public function mount()
    {
        if (auth()->user()->role !== 'kasir') {
            abort(403, 'Akses Ditolak: Hanya Kasir yang berhak mengakses halaman ini.');
        }
    }

    public function addToCart($productId)
    {
        $product = Product::with('ingredients')->find($productId);
        if (! $product) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty'] += 1;
        } else {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'qty' => 1,
            ];
        }
    }

    public function removeFromCart($productId)
    {
        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['qty'] > 1) {
                $this->cart[$productId]['qty'] -= 1;
            } else {
                unset($this->cart[$productId]);
            }
        }
    }

    public function getTotal(): int
    {
        return array_sum(array_map(fn ($item) => $item['price'] * $item['qty'], $this->cart));
    }

    public function getPotonganCalculated(): int
    {
        if (! $this->currentKaryawan) {
            return 0;
        }

        return min($this->getTotal(), $this->currentKaryawan->getSisaPotongan());
    }

    public function getTotalAfterPotongan(): int
    {
        return max(0, $this->getTotal() - $this->getPotonganCalculated());
    }

    public function scanKaryawan()
    {
        $this->karyawanError = '';
        $this->currentKaryawan = null;

        if (empty(trim($this->nikInput))) {
            $this->karyawanError = 'NIK tidak boleh kosong.';

            return;
        }

        $karyawan = auth()->user()->branch->karyawans()
            ->where('nik', trim($this->nikInput))
            ->first();

        if (! $karyawan) {
            $this->karyawanError = 'Karyawan dengan NIK tersebut tidak ditemukan.';

            return;
        }

        if (! $karyawan->is_active) {
            $this->karyawanError = 'Karyawan tersebut tidak aktif.';

            return;
        }

        if (! $karyawan->isDalamJamKerja()) {
            $this->karyawanError = 'Karyawan tersebut sedang tidak dalam jam kerja ('.$karyawan->jam_mulai.' - '.$karyawan->jam_selesai.').';

            return;
        }

        $this->currentKaryawan = $karyawan;
    }

    public function clearKaryawan()
    {
        $this->nikInput = '';
        $this->currentKaryawan = null;
        $this->karyawanError = '';
        $this->karyawanPaymentMethod = 'qris_edc';
    }

    public function processOrder()
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Keranjang kosong.');

            return;
        }

        if (! $this->currentKaryawan) {
            session()->flash('error', 'Silakan scan NIK karyawan terlebih dahulu.');

            return;
        }

        $total = $this->getTotal();

        DB::transaction(function () use ($total) {
            $sisaPotongan = $this->currentKaryawan->getSisaPotongan();
            $potongan = min($total, $sisaPotongan);
            $totalSetelahPotongan = max(0, $total - $potongan);

            $orderNumber = 'ORD-'.date('YmdHis').strtoupper(uniqid());
            $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(uniqid());
            $metodePembayaran = $totalSetelahPotongan === 0 ? 'jatah_harian' : ($this->karyawanPaymentMethod === 'potong_gaji' ? 'potong_gaji' : 'qris_edc');

            $order = auth()->user()->branch->orders()->create([
                'order_number' => $orderNumber,
                'username_cashier' => auth()->user()->name,
                'customer_name' => $this->currentKaryawan->nama.' (Karyawan)',
                'table_number' => 'Take Away',
                'total_price' => $total,
                'status' => 'paid',
                'kitchen_status' => 'served',
            ]);

            $transaction = auth()->user()->branch->transactions()->create([
                'username_cashier' => auth()->user()->name,
                'customer_name' => $this->currentKaryawan->nama.' (Karyawan)',
                'table_number' => 'Take Away',
                'invoice_number' => $invoiceNumber,
                'total_amount' => $total,
                'paid_amount' => $total,
                'change_amount' => 0,
                'payment_method' => $metodePembayaran,
                'status' => 'completed',
                'karyawan_id' => $this->currentKaryawan->id,
                'dibayar_perusahaan' => $potongan,
                'dibayar_karyawan' => $totalSetelahPotongan,
            ]);

            foreach ($this->cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                ]);

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

        $this->reset(['cart', 'nikInput', 'currentKaryawan', 'karyawanError', 'karyawanPaymentMethod']);
        session()->flash('success', 'Pesanan karyawan berhasil diproses!');
    }

    protected function deductProductIngredients(array $item, string $orderNumber)
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
                    'reference_id' => $orderNumber,
                ]);

                $ingredient->decrement('current_stock', $totalIngredientUsed);
            }
        }
    }

    public function with(): array
    {
        return [
            'products' => auth()->user()->branch->products()->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Lunch Karyawan') }}
            </h2>
        </div>
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

            <div class="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                    </div>
                    <div>
                        <p class="font-bold text-amber-800 leading-none">Scan NIK Karyawan</p>
                        <p class="text-xs text-amber-600 mt-1">Scan atau input NIK untuk menerapkan potongan makan siang</p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <div class="flex-1">
                        <input type="text" wire:model="nikInput" placeholder="Scan atau input NIK..." 
                            class="w-full border-amber-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500"
                            x-data="{}"
                            x-on:keydown.enter.window="$wire.scanKaryawan()">
                    </div>
                    <button wire:click="scanKaryawan" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-bold text-sm transition">
                        Cek
                    </button>
                </div>
                
                @if($karyawanError)
                    <div class="mt-2 text-red-600 text-sm font-medium">
                        {{ $karyawanError }}
                    </div>
                @endif
                
                @if($currentKaryawan)
                    <div class="mt-3 p-3 bg-white border border-amber-200 rounded-lg">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-bold text-gray-800">{{ $currentKaryawan->nama }}</p>
                                <p class="text-xs text-gray-500">NIK: {{ $currentKaryawan->nik }}</p>
                                <p class="text-xs text-gray-500">Jam Kerja: {{ $currentKaryawan->jam_mulai }} - {{ $currentKaryawan->jam_selesai }}</p>
                            </div>
                            <button wire:click="clearKaryawan" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                Batal
                            </button>
                        </div>
                        <div class="mt-2 pt-2 border-t border-gray-100 flex justify-between text-sm">
                            <span class="text-gray-600">Limit Harian:</span>
                            <span class="font-bold text-amber-600">Rp {{ number_format($currentKaryawan->limit_potongan_harian, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Sisa Hari Ini:</span>
                            <span class="font-bold text-green-600">Rp {{ number_format($currentKaryawan->getSisaPotongan(), 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <div class="w-full lg:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" style="height: calc(100vh - 220px);">
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Pilih Menu</h3>
                    
                    @if(count($products) > 0)
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 overflow-y-auto" style="max-height: calc(100% - 50px);">
                            @foreach($products as $product)
                                <button wire:click="addToCart({{ $product->id }})"
                                    class="p-3 border-2 border-gray-200 rounded-xl hover:border-amber-400 hover:bg-amber-50 transition text-left">
                                    <p class="font-semibold text-gray-800 text-sm">{{ $product->name }}</p>
                                    <p class="text-amber-600 font-bold mt-1">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-gray-500 py-12">
                            <p>Belum ada menu tersedia.</p>
                        </div>
                    @endif
                </div>

                <div class="w-full lg:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-0 flex flex-col" style="height: calc(100vh - 220px);">
                    <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-700">Keranjang</h3>
                        @if(count($cart) > 0)
                            <button wire:click="$set('cart', [])" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                Clear
                            </button>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        @forelse($cart as $id => $item)
                            <div class="flex justify-between items-center border-b pb-2">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 text-sm">{{ $item['name'] }}</h4>
                                    <p class="text-gray-500 text-xs">Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="removeFromCart({{ $id }})" class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-xs font-bold">-</button>
                                    <span class="font-bold text-sm w-6 text-center">{{ $item['qty'] }}</span>
                                    <button wire:click="addToCart({{ $id }})" class="w-6 h-6 rounded-full bg-amber-200 hover:bg-amber-300 flex items-center justify-center text-xs font-bold">+</button>
                                </div>
                                <div class="text-right w-20">
                                    <p class="font-bold text-sm">Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-400 py-10">
                                <p>Keranjang kosong</p>
                                <p class="text-xs mt-1">Pilih menu di sebelah</p>
                            </div>
                        @endforelse
                    </div>

                    @if(count($cart) > 0)
                    <div class="p-4 bg-gray-50 border-t space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 font-semibold">Total</span>
                            <span class="text-2xl font-bold text-gray-800">Rp {{ number_format($total = $this->getTotal(), 0, ',', '.') }}</span>
                        </div>

                        @if($currentKaryawan)
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex justify-between items-center text-sm mb-1">
                                    <span class="text-gray-600">Potongan Makan:</span>
                                    <span class="font-bold text-green-600">Rp {{ number_format($potongan = $this->getPotonganCalculated(), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-green-200">
                                    <span class="text-gray-800 font-semibold">Total Dibayar:</span>
                                    <span class="text-xl font-bold text-gray-800">Rp {{ number_format($totalAfterPotongan = $this->getTotalAfterPotongan(), 0, ',', '.') }}</span>
                                </div>
                            </div>

                            @if($this->getTotalAfterPotongan() > 0)
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Metode Pembayaran</label>
                                    <select wire:model="karyawanPaymentMethod" class="w-full border-gray-300 rounded-md text-sm focus:ring-amber-500 focus:border-amber-500">
                                        <option value="qris_edc">QRIS EDC</option>
                                        <option value="potong_gaji">Potong Gaji</option>
                                    </select>
                                </div>

                                @if($karyawanPaymentMethod === 'potong_gaji')
                                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-center">
                                        <p class="text-sm text-purple-700 font-medium">Pembayaran akan dipotong dari gaji karyawan</p>
                                    </div>
                                @endif
                            @else
                                <div class="p-3 bg-green-100 border border-green-300 rounded-lg text-center">
                                    <p class="text-green-700 font-bold">Gratis - Tidak perlu pembayaran</p>
                                </div>
                            @endif
                        @else
                            <div class="p-3 bg-gray-100 border border-gray-200 rounded-lg text-center">
                                <p class="text-sm text-gray-500">Scan NIK karyawan untuk memproses pesanan</p>
                            </div>
                        @endif

                        <button wire:click="processOrder" @if(!$currentKaryawan) disabled @endif
                            class="w-full bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg shadow-md transition flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>{{ $this->getTotalAfterPotongan() === 0 ? 'Konfirmasi Pesanan (Gratis)' : ($karyawanPaymentMethod === 'potong_gaji' ? 'Konfirmasi Potong Gaji' : 'Proses Pembayaran') }}</span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>