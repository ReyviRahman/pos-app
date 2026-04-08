<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

new class extends Component {
    public $cart = [];
    public $customer_name = '';
    public $table_number = '';
    public $search = '';

    public function mount()
    {
        if (auth()->user()->role !== 'waiter') {
            abort(403, 'Akses Ditolak: Hanya Waiter yang berhak mengakses halaman pemesanan.');
        }
    }

    // Menggunakan Computed property agar pencarian reaktif
    #[Computed]
    public function products()
    {
        return Product::where('name', 'like', '%' . $this->search . '%')->get();
    }

    public function submitOrderAlpine($cartData)
    {
        if (empty($cartData)) {
            $this->dispatch('custom-notify', message: 'Pilih minimal satu menu dulu!', type: 'error');
            return;
        }

        $this->cart = $cartData;

        // Validasi input
        $this->validate([
            'customer_name' => 'required|string|max:255',
            'table_number' => 'required|string|max:10',
        ], [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'table_number.required' => 'Nomor meja wajib diisi.',
        ]);

        $totalPrice = collect($this->cart)->sum(function ($item) {
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

        $this->dispatch('custom-notify', message: 'Pesanan berhasil dikirim ke dapur!', type: 'success');
        $this->dispatch('cart-cleared');
    }
};
?>

<div class="flex flex-col lg:flex-row min-h-screen lg:h-screen bg-slate-50 font-sans relative lg:overflow-hidden"
    x-data="orderCart()" @cart-cleared.window="cart = {}" @custom-notify.window="showNotification($event.detail.message, $event.detail.type)">

    <!-- TOAST SUCCESS -->
    <div x-cloak x-show="toastType === 'success'" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-x-8"
        x-transition:enter-end="opacity-100 transform translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-x-0"
        x-transition:leave-end="opacity-0 transform translate-x-8"
        class="fixed top-8 right-4 lg:right-8 bg-emerald-600 text-white px-5 sm:px-6 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-4 min-w-[280px] sm:min-w-[320px] ring-4 ring-emerald-500/30">
        <div class="bg-emerald-500/50 p-2 sm:p-2.5 rounded-full flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <div class="flex flex-col">
            <span class="font-black text-lg leading-none mb-1">Berhasil!</span>
            <span class="font-medium text-emerald-100 text-sm" x-text="toastMessage"></span>
        </div>
        <button @click="toastType = ''" class="ml-auto text-emerald-200 hover:text-white transition focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- TOAST ERROR -->
    <div x-cloak x-show="toastType === 'error'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-x-8"
        x-transition:enter-end="opacity-100 transform translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-x-0"
        x-transition:leave-end="opacity-0 transform translate-x-8"
        class="fixed top-8 right-4 lg:right-8 bg-rose-600 text-white px-5 sm:px-6 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-4 min-w-[280px] sm:min-w-[320px] ring-4 ring-rose-500/30">
        <div class="bg-rose-500/50 p-2 sm:p-2.5 rounded-full flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="flex flex-col">
            <span class="font-black text-lg leading-none mb-1">Gagal!</span>
            <span class="font-medium text-rose-100 text-sm" x-text="toastMessage"></span>
        </div>
        <button @click="toastType = ''" class="ml-auto text-rose-200 hover:text-white transition focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <div class="flex-1 p-4 sm:p-6 lg:p-8 lg:overflow-y-auto w-full">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 sm:mb-8">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Daftar Menu</h2>

            <div class="relative w-full sm:w-72">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari menu..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
            @forelse($this->products as $product)
                <div wire:key="product-{{ $product->id }}"
                    @click="addToCart({ id: {{ $product->id }}, name: '{{ addslashes($product->name) }}', price: {{ $product->price }} })"
                    class="bg-white p-5 rounded-2xl shadow-sm cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200 border border-slate-100 group">

                    <div
                        class="h-36 bg-slate-100 rounded-xl mb-4 flex items-center justify-center text-slate-300 group-hover:bg-indigo-50 transition">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg leading-tight mb-1 line-clamp-1">{{ $product->name }}</h3>
                    <p class="text-indigo-600 font-bold">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                </div>
            @empty
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-lg">Menu tidak ditemukan</p>
                </div>
            @endforelse
        </div>
    </div>

    <div
        class="w-full lg:w-[420px] bg-white shadow-2xl flex flex-col z-10 lg:border-l border-t lg:border-t-0 border-slate-100 flex-shrink-0">
        <div class="p-4 sm:p-6 border-b border-slate-100">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800">Pesanan Saat Ini</h2>
        </div>

        <div class="p-4 sm:p-6 space-y-4 border-b border-slate-100 bg-slate-50/50">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nama Pelanggan</label>
                <input wire:model="customer_name" type="text" placeholder="Masukkan nama..."
                    class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                @error('customer_name') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Meja</label>
                <input wire:model="table_number" type="text" placeholder="Contoh: 12"
                    class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                @error('table_number') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex-1 lg:overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50">

            <template x-if="cartItems.length > 0">
                <template x-for="item in cartItems" :key="item.id">
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-bold text-slate-800" x-text="item.name"></p>
                                <p class="text-indigo-600 font-semibold text-sm" x-text="formatRupiah(item.price)"></p>
                            </div>
                            <p class="font-bold text-slate-800" x-text="formatRupiah(item.price * item.quantity)"></p>
                        </div>

                        <div class="flex items-center justify-between mt-2">
                            <div class="flex items-center bg-slate-100 rounded-lg p-1">
                                <button @click="decreaseQuantity(item.id)"
                                    class="w-8 h-8 flex items-center justify-center bg-white rounded shadow-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 transition">-</button>
                                <span class="w-10 text-center font-bold text-slate-800" x-text="item.quantity"></span>
                                <button @click="increaseQuantity(item.id)"
                                    class="w-8 h-8 flex items-center justify-center bg-white rounded shadow-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 transition">+</button>
                            </div>
                            <button @click="removeFromCart(item.id)"
                                class="text-rose-500 hover:text-rose-700 text-sm font-bold flex items-center gap-1 bg-rose-50 px-3 py-1.5 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                                Hapus
                            </button>
                        </div>
                    </div>
                </template>
            </template>

            <template x-if="cartItems.length === 0">
                <div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    <p class="text-center font-medium">Keranjang masih kosong</p>
                </div>
            </template>
        </div>

        <div class="p-4 sm:p-6 border-t border-slate-100 bg-white lg:sticky bottom-0">
            <div class="flex justify-between items-center mb-4 sm:mb-6">
                <span class="text-slate-500 font-medium">Total Pembayaran</span>
                <span class="text-2xl font-extrabold text-indigo-600" x-text="formatRupiah(totalPrice)"></span>
            </div>
            <button @click="submitOrder"
                class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Kirim Pesanan ke Dapur
            </button>
        </div>
    </div>

    <!-- AlpineJS Component Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('orderCart', () => ({
                cart: {},
                toastMessage: '',
                toastType: '',
                toastTimeout: null,
                
                showNotification(message, type) {
                    this.toastMessage = message;
                    this.toastType = type;
                    if (this.toastTimeout) clearTimeout(this.toastTimeout);
                    this.toastTimeout = setTimeout(() => {
                        this.toastType = '';
                    }, 3500);
                },
                get cartItems() {
                    return Object.values(this.cart);
                },
                addToCart(product) {
                    if (this.cart[product.id]) {
                        this.cart[product.id].quantity++;
                    } else {
                        this.cart[product.id] = { ...product, quantity: 1 };
                    }
                },
                increaseQuantity(id) {
                    if (this.cart[id]) this.cart[id].quantity++;
                },
                decreaseQuantity(id) {
                    if (this.cart[id]) {
                        if (this.cart[id].quantity > 1) {
                            this.cart[id].quantity--;
                        } else {
                            delete this.cart[id];
                        }
                    }
                },
                removeFromCart(id) {
                    delete this.cart[id];
                },
                get totalPrice() {
                    return this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },
                submitOrder() {
                    if (this.cartItems.length === 0) {
                        alert('Pilih minimal satu menu dulu!');
                        return;
                    }
                    this.$wire.submitOrderAlpine(this.cartItems);
                },
                formatRupiah(number) {
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0
                    }).format(number);
                }
            }))
        })
    </script>
</div>