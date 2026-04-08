<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

new class extends Component
{
    public $cart = [];
    public $customer_name = '';
    public $table_number = '';
    public $search = '';

    // Menggunakan Computed property agar pencarian reaktif
    #[Computed]
    public function products()
    {
        return Product::where('name', 'like', '%' . $this->search . '%')->get();
    }

    public function addToCart($productId)
    {
        $product = $this->products->firstWhere('id', $productId);
        if (!$product) {
            $product = Product::find($productId);
        }

        if (!$product) return;

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity']++;
        } else {
            $this->cart[$productId] = [
                'id' => $product->id, 
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 1,
            ];
        }
    }

    public function increaseQuantity($productId)
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity']++;
        }
    }

    public function decreaseQuantity($productId)
    {
        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] > 1) {
                $this->cart[$productId]['quantity']--;
            } else {
                $this->removeFromCart($productId);
            }
        }
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
    }

    public function submitOrder()
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Pilih minimal satu menu dulu!');
            return;
        }

        // Validasi input
        $this->validate([
            'customer_name' => 'required|string|max:255',
            'table_number' => 'required|string|max:10',
        ], [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'table_number.required' => 'Nomor meja wajib diisi.',
        ]);

        $totalPrice = collect($this->cart)->sum(function($item) {
            return $item['price'] * $item['quantity'];
        });

        $order = Order::create([
            'order_number' => 'ORD-' . now()->format('YmdHis'),
            'username_cashier' => auth()->user()->name ?? 'System',
            'customer_name' => $this->customer_name,
            'table_number' => $this->table_number,
            'total_price' => $totalPrice,
            'status' => 'unpaid',
        ]);

        foreach ($this->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        $this->reset(['cart', 'customer_name', 'table_number', 'search']);
        
        session()->flash('success', 'Pesanan berhasil dikirim ke dapur!');
    }
};
?>

<div class="flex h-screen bg-slate-50 font-sans overflow-hidden relative">
    
    @if (session()->has('success'))
        <div class="absolute top-6 right-6 bg-emerald-500 text-white px-6 py-4 rounded-xl shadow-lg z-50 flex items-center gap-3 animate-bounce">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span class="font-semibold">{{ session('success') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="absolute top-6 right-6 bg-rose-500 text-white px-6 py-4 rounded-xl shadow-lg z-50 flex items-center gap-3 animate-bounce">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="font-semibold">{{ session('error') }}</span>
        </div>
    @endif

    <div class="flex-1 p-8 overflow-y-auto w-full">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Daftar Menu</h2>
            
            <div class="relative w-72">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari menu..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition">
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($this->products as $product)
                <div wire:key="product-{{ $product->id }}" 
                    wire:click="addToCart({{ $product->id }})" 
                    class="bg-white p-5 rounded-2xl shadow-sm cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200 border border-slate-100 group">
                    
                    <div class="h-36 bg-slate-100 rounded-xl mb-4 flex items-center justify-center text-slate-300 group-hover:bg-indigo-50 transition">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg leading-tight mb-1 line-clamp-1">{{ $product->name }}</h3>
                    <p class="text-indigo-600 font-bold">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                </div>
            @empty
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-lg">Menu tidak ditemukan</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="w-[420px] bg-white shadow-2xl flex flex-col z-10 border-l border-slate-100">
        <div class="p-6 border-b border-slate-100">
            <h2 class="text-2xl font-bold text-slate-800">Pesanan Saat Ini</h2>
        </div>

        <div class="p-6 space-y-4 border-b border-slate-100 bg-slate-50/50">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nama Pelanggan</label>
                <input wire:model="customer_name" type="text" placeholder="Masukkan nama..." class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                @error('customer_name') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Meja</label>
                <input wire:model="table_number" type="text" placeholder="Contoh: 12" class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                @error('table_number') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50">
            @forelse($cart as $id => $item)
                <div wire:key="cart-{{ $id }}" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <p class="font-bold text-slate-800">{{ $item['name'] }}</p>
                            <p class="text-indigo-600 font-semibold text-sm">Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                        </div>
                        <p class="font-bold text-slate-800">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</p>
                    </div>
                    
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center bg-slate-100 rounded-lg p-1">
                            <button wire:click="decreaseQuantity({{ $id }})" class="w-8 h-8 flex items-center justify-center bg-white rounded shadow-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 transition">-</button>
                            <span class="w-10 text-center font-bold text-slate-800">{{ $item['quantity'] }}</span>
                            <button wire:click="increaseQuantity({{ $id }})" class="w-8 h-8 flex items-center justify-center bg-white rounded shadow-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 transition">+</button>
                        </div>
                        <button wire:click="removeFromCart({{ $id }})" class="text-rose-500 hover:text-rose-700 text-sm font-bold flex items-center gap-1 bg-rose-50 px-3 py-1.5 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            Hapus
                        </button>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <p class="text-center font-medium">Keranjang masih kosong</p>
                </div>
            @endforelse
        </div>

        <div class="p-6 border-t border-slate-100 bg-white">
            <div class="flex justify-between items-center mb-6">
                <span class="text-slate-500 font-medium">Total Pembayaran</span>
                <span class="text-2xl font-extrabold text-indigo-600">
                    Rp {{ number_format(collect($cart)->sum(fn($item) => $item['price'] * $item['quantity']), 0, ',', '.') }}
                </span>
            </div>
            <button wire:click="submitOrder" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Kirim Pesanan ke Dapur
            </button>
        </div>
    </div>
</div>