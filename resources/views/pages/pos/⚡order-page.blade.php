<?php

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $cart = [];

    public $customer_name = '';

    public $table_number = '';

    public $search = '';

    public $selected_order_id = null;

    public $note = '';

    public $showConfirmModal = false;

    public $page = 1;

    public $perPage = 20;

    public function mount()
    {
        if (auth()->user()->role !== 'waiter') {
            abort(403, 'Akses Ditolak: Hanya Waiter yang berhak mengakses halaman pemesanan.');
        }
    }

    public function previousPage()
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function nextPage()
    {
        $this->page++;
    }

    #[Computed]
    public function products()
    {
        return auth()->user()->branch->products()
            ->where('name', 'like', '%'.$this->search.'%')
            ->orderBy('name')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    #[Computed]
    public function activeOrders()
    {
        return auth()->user()->branch->orders()->with('items.product')
            ->whereIn('kitchen_status', ['pending', 'cooking', 'completed'])
            ->orderByRaw("FIELD(kitchen_status, 'completed', 'cooking', 'pending')")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function selectedOrderCookedItems()
    {
        if (! $this->selected_order_id) {
            return [];
        }

        $order = Order::with('items')->find($this->selected_order_id);
        if (! $order) {
            return [];
        }

        $cookedProductIds = [];
        foreach ($order->items as $item) {
            if (in_array($item->kitchen_status, ['cooking', 'completed', 'served'])) {
                $cookedProductIds[] = $item->product_id;
            }
        }

        return array_unique($cookedProductIds);
    }

    public function selectActiveOrder($orderId)
    {
        $order = auth()->user()->branch->orders()->with('items.product')->findOrFail($orderId);
        $this->selected_order_id = $order->id;
        $this->customer_name = $order->customer_name;
        $this->table_number = $order->table_number;
        $this->note = $order->note;

        $cartItems = [];
        foreach ($order->items as $item) {
            if ($item->product) {
                if (isset($cartItems[$item->product_id])) {
                    $cartItems[$item->product_id]['quantity'] += $item->quantity;
                } else {
                    $cartItems[$item->product_id] = [
                        'id' => $item->product_id,
                        'name' => $item->product->name,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                    ];
                }
            }
        }
        $this->dispatch('load-cart-items', items: array_values($cartItems));
        $this->dispatch('custom-notify', message: 'Pesanan dimuat ke keranjang untuk diedit', type: 'success');
    }

    public function clearSelectedOrder()
    {
        $this->selected_order_id = null;
        $this->customer_name = '';
        $this->table_number = '';
        $this->note = '';
        $this->dispatch('custom-notify', message: 'Batal menambahkan ke pesanan yang ada', type: 'success');
    }

    public function markAsServed($orderId)
    {
        $order = Order::where('branch_id', auth()->user()->branch_id)->findOrFail($orderId);
        $order->update(['kitchen_status' => 'served']);
        $order->items()->update(['kitchen_status' => 'served']);
        $this->dispatch('custom-notify', message: 'Pesanan ditandai sebagai selesai diberikan ke pelanggan!', type: 'success');
    }

    public function submitOrderAlpine($cartData)
    {
        if (empty($cartData) && ! $this->selected_order_id) {
            $this->dispatch('custom-notify', message: 'Pilih minimal satu menu dulu!', type: 'error');

            return;
        }

        $this->showConfirmModal = false;

        // Validasi input nama dan nomor meja
        $this->validate([
            'customer_name' => 'required|string|max:255',
            'table_number' => 'required|string|max:10',
        ], [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'table_number.required' => 'Nomor meja wajib diisi.',
        ]);

        // 1. Ambil semua ID produk yang dikirim dari browser
        $productIds = collect($cartData)->pluck('id')->toArray();

        // 2. QUERY DATABASE: Ambil HANYA produk yang ADA di CABANG USER SAAT INI
        // Ini mengamankan celah "produk cabang lain"
        $validProducts = auth()->user()->branch->products()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id'); // Jadikan ID sebagai key agar mudah dicari

        $totalPrice = 0;
        $validOrderItems = [];

        // 3. Looping data cart dari frontend, tapi bandingkan dengan data valid dari database
        foreach ($cartData as $item) {
            $productId = $item['id'];

            // Jika ID produk dimanipulasi dan tidak ada di cabang ini, lewati!
            if (! $validProducts->has($productId)) {
                continue;
            }

            $product = $validProducts[$productId];
            $quantity = (int) $item['quantity'];

            if ($quantity <= 0) {
                continue;
            } // Mencegah quantity minus

            // AMBIL HARGA DARI DATABASE, BUKAN DARI FRONTEND
            $totalPrice += ($product->price * $quantity);

            $validOrderItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price, // Harga asli dari DB
            ];
        }

        // Jika setelah divalidasi ternyata kosong, padahal cartData dikirim (semua ID palsu)
        if (empty($validOrderItems) && ! empty($cartData)) {
            $this->dispatch('custom-notify', message: 'Produk tidak valid!', type: 'error');

            return;
        }

        // 4. Buat / Update Order
        if ($this->selected_order_id) {
            // UPDATE EXISTING ORDER
            $order = auth()->user()->branch->orders()->find($this->selected_order_id);
            if (! $order) {
                $this->dispatch('custom-notify', message: 'Order tidak ditemukan!', type: 'error');

                return;
            }

            // TOTAL HARGA langsung dioverride sesuai state keranjang saat ini
            $order->total_price = $totalPrice;
            $order->customer_name = $this->customer_name;
            $order->table_number = $this->table_number;
            $order->note = $this->note;

            // Logic Sinkronisasi per produk
            $existingItems = OrderItem::with('product')->where('order_id', $order->id)->get()->groupBy('product_id');

            // Validasi: Jangan biarkan mengurangi QTY di bawah jumlah yang sudah terlanjur diproses dapur
            foreach ($existingItems as $productId => $oldItems) {
                $alreadyProcessedQty = collect($oldItems)->whereIn('kitchen_status', ['cooking', 'completed', 'served'])->sum('quantity');

                if ($alreadyProcessedQty > 0) {
                    $newQty = 0;
                    foreach ($validOrderItems as $vvi) {
                        if ($vvi['product_id'] == $productId) {
                            $newQty = $vvi['quantity'];
                            break;
                        }
                    }

                    if ($newQty < $alreadyProcessedQty) {
                        $productName = collect($oldItems)->first()->product->name ?? 'Menu';
                        $this->dispatch('custom-notify', message: "Gagal! {$productName} sudah diproses dapur sebanyak {$alreadyProcessedQty} porsi. Tidak bisa diperbarui di bawah angka tersebut.", type: 'error');

                        return;
                    }
                }
            }

            $newProductIds = collect($validOrderItems)->pluck('product_id')->toArray();

            // 1. Hapus produk yang tidak ada di keranjang baru
            $productIdsToRemove = $existingItems->keys()->diff($newProductIds);
            foreach ($productIdsToRemove as $pid) {
                OrderItem::where('order_id', $order->id)->where('product_id', $pid)->delete();
            }

            $addedNewItems = false;

            // 2. Adjust Kuantitas
            foreach ($validOrderItems as $itemData) {
                $newQty = $itemData['quantity'];
                $oldItems = $existingItems->get($itemData['product_id'], collect());
                $oldQty = $oldItems->sum('quantity');

                if ($newQty > $oldQty) {
                    $addedNewItems = true;
                    // Tambah kekurangannya sebagai item baru (status pending)
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $itemData['product_id'],
                        'quantity' => $newQty - $oldQty,
                        'price' => $itemData['price'],
                        'kitchen_status' => 'pending',
                    ]);
                } elseif ($newQty < $oldQty) {
                    $qtyToRemove = $oldQty - $newQty;
                    // Prioritaskan menghapus status pending terlebih dahulu
                    $sortedOld = $oldItems->sortByDesc(function ($i) {
                        return $i->kitchen_status === 'pending' ? 3 : ($i->kitchen_status === 'cooking' ? 2 : 1);
                    });

                    foreach ($sortedOld as $oldI) {
                        if ($qtyToRemove <= 0) {
                            break;
                        }
                        if ($oldI->quantity <= $qtyToRemove) {
                            $qtyToRemove -= $oldI->quantity;
                            $oldI->delete();
                        } else {
                            $oldI->quantity -= $qtyToRemove;
                            $oldI->save();
                            $qtyToRemove = 0;
                        }
                    }
                }
            }

            // Ubah status dapur ke pending HANYA jika ada unit tambahan baru
            if ($addedNewItems) {
                if ($order->kitchen_status === 'completed' || $order->kitchen_status === 'served') {
                    $order->kitchen_status = 'pending';
                }
            }
            $order->save();

            $this->dispatch('custom-notify', message: 'Pesanan berhasil diupdate!', type: 'success');
        } else {
            // BUAT ORDER BARU (Tetap aman seperti kodemu sebelumnya)
            $order = auth()->user()->branch->orders()->create([
                'order_number' => 'ORD-'.now()->format('YmdHis'),
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->customer_name,
                'table_number' => $this->table_number,
                'total_price' => $totalPrice, // Total harga yang sudah dihitung ulang dari DB
                'status' => 'unpaid',
                'note' => $this->note,
            ]);

            // 5. Masukkan item yang sudah valid
            foreach ($validOrderItems as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }
            $this->dispatch('custom-notify', message: 'Pesanan baru berhasil dikirim ke dapur!', type: 'success');
        }

        $this->reset(['cart', 'customer_name', 'table_number', 'search', 'selected_order_id', 'note']);
        $this->dispatch('cart-cleared');
    }
};
?>

<div class="flex flex-col lg:flex-row min-h-screen lg:min-h-screen bg-slate-50 font-sans relative lg:overflow-hidden"
    x-data="orderCart()" @cart-cleared.window="cart = {}"
    @custom-notify.window="showNotification($event.detail.message, $event.detail.type)"
    @load-cart-items.window="
        cart = {};
        $event.detail.items.forEach(item => {
            cart[item.id] = item;
        });
    ">

    <!-- TOAST SUCCESS -->
    <div x-cloak x-show="toastType === 'success'" x-transition:enter="transition ease-out duration-300"
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
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- TOAST ERROR -->
    <div x-cloak x-show="toastType === 'error'" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-x-8"
        x-transition:enter-end="opacity-100 transform translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-x-0"
        x-transition:leave-end="opacity-0 transform translate-x-8"
        class="fixed top-8 right-4 lg:right-8 bg-rose-600 text-white px-5 sm:px-6 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-4 min-w-[280px] sm:min-w-[320px] ring-4 ring-rose-500/30">
        <div class="bg-rose-500/50 p-2 sm:p-2.5 rounded-full flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="flex flex-col">
            <span class="font-black text-lg leading-none mb-1">Gagal!</span>
            <span class="font-medium text-rose-100 text-sm" x-text="toastMessage"></span>
        </div>
        <button @click="toastType = ''" class="ml-auto text-rose-200 hover:text-white transition focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
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

        <!-- DAFTAR PESANAN AKTIF -->
        <div wire:poll.5s.visible class="mb-8 hidden sm:block">
            <h3 class="text-lg font-bold text-slate-700 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Status Pesanan Aktif
            </h3>
            
            @if(count($this->activeOrders) > 0)
                <div class="flex gap-4 overflow-x-auto pb-4 hide-scrollbar">
                    @foreach($this->activeOrders as $activeOrder)
                        <div wire:key="active-order-{{ $activeOrder->id }}" x-data="{ showDetails: false }" class="min-w-[260px] bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex-shrink-0 flex flex-col justify-between relative">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-black text-slate-800">{{ $activeOrder->table_number ? 'Meja ' . $activeOrder->table_number : 'Take Away' }}</p>
                                    <p class="text-xs font-medium text-slate-500 line-clamp-1">{{ $activeOrder->customer_name }}</p>
                                </div>
                                <span class="bg-indigo-50 text-indigo-600 text-xs font-bold px-2 py-1 rounded-lg">{{ count($activeOrder->items) }} Item</span>
                            </div>
                            
                            <div class="my-2 flex-1 flex flex-col items-start gap-2">
                                <button @click="showDetails = true" class="text-xs text-indigo-600 font-bold hover:text-indigo-800 underline flex items-center gap-1 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    Lihat Detail Pesanan
                                </button>
                                @if($activeOrder->status === 'unpaid')
                                <button type="button" wire:click="selectActiveOrder({{ $activeOrder->id }})" class="text-xs text-emerald-600 font-bold hover:text-emerald-800 underline flex items-center gap-1 transition w-fit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit Data Pesanan
                                </button>
                                @endif
                            </div>
                            
                            <!-- Modal Detail -->
                            <div x-show="showDetails" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
                                <div @click.outside="showDetails = false" class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl relative" x-transition>
                                    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                        <h4 class="font-bold text-slate-800 text-lg">Detail Pesanan</h4>
                                        <button @click="showDetails = false" class="text-slate-400 hover:text-rose-500 transition">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                    <div class="p-4 max-h-[60vh] overflow-y-auto">
                                        <div class="mb-4 bg-indigo-50 text-indigo-800 p-3 rounded-xl border border-indigo-100">
                                            <span class="font-black block text-base">{{ $activeOrder->table_number ? 'Meja ' . $activeOrder->table_number : 'Take Away' }}</span>
                                            <span class="text-sm font-medium">Atas Nama: {{ $activeOrder->customer_name }}</span>
                                            @if($activeOrder->note)
                                                <div class="mt-2 text-sm text-indigo-700 bg-white/50 p-2.5 rounded border border-indigo-200">
                                                    <strong>Catatan:</strong> {{ $activeOrder->note }}
                                                </div>
                                            @endif
                                        </div>
                                        <ul class="text-sm text-slate-600 space-y-3">
                                            @foreach($activeOrder->items as $item)
                                                <li class="flex justify-between items-start border-b border-slate-50 pb-2">
                                                    <span class="font-bold text-indigo-700 bg-indigo-100 px-2.5 py-1 rounded-lg mr-3 shadow-sm">{{ $item->quantity }}x</span>
                                                    <span class="flex-1 font-medium mt-0.5">{{ $item->product_name ?? ($item->product ? $item->product->name : 'Item') }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-auto pt-2 border-t border-slate-100">
                                @if($activeOrder->kitchen_status === 'pending')
                                    <div class="flex items-center gap-2 text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg text-xs font-bold w-full justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Menunggu Dapur
                                    </div>
                                @elseif($activeOrder->kitchen_status === 'cooking')
                                    <div class="flex items-center gap-2 text-amber-700 bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold w-full justify-center">
                                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        Sedang Dimasak
                                    </div>
                                @elseif($activeOrder->kitchen_status === 'completed')
                                    <button wire:click="markAsServed({{ $activeOrder->id }})" class="flex items-center justify-center gap-2 text-white bg-emerald-500 hover:bg-emerald-600 px-3 py-2 rounded-lg text-xs font-bold w-full transition shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Siap Dihidangkan & Selesai
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white/50 border border-slate-200 border-dashed rounded-xl p-4 text-center text-sm font-medium text-slate-400">
                    Tidak ada pesanan aktif saat ini.
                </div>
            @endif
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

        @if($this->products->lastPage() > 1)
        <div class="mt-6 flex justify-center">
            <div class="flex gap-2">
                @if($this->products->onFirstPage())
                    <span class="px-4 py-2 bg-slate-200 text-slate-400 rounded-lg cursor-not-allowed">‹</span>
                @else
                    <button wire:click="previousPage" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">‹</button>
                @endif
                
                <span class="px-4 py-2 text-slate-600 font-medium">{{ $this->products->currentPage() }} / {{ $this->products->lastPage() }}</span>
                
                @if($this->products->currentPage() >= $this->products->lastPage())
                    <span class="px-4 py-2 bg-slate-200 text-slate-400 rounded-lg cursor-not-allowed">›</span>
                @else
                    <button wire:click="nextPage" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">›</button>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div
        class="w-full lg:w-[420px] bg-white shadow-2xl flex flex-col z-10 lg:border-l border-t lg:border-t-0 border-slate-100 flex-shrink-0">
            @if($selected_order_id)
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-700 p-3 rounded-xl flex justify-between items-center shadow-sm">
                    <div>
                        <span class="text-[10px] font-black block uppercase tracking-wider text-indigo-500 mb-0.5">Mode Edit Pesanan</span>
                        <span class="font-bold text-sm">Mengubah {{ $table_number ? 'Meja ' . $table_number : 'Take Away' }}</span>
                    </div>
                    <button wire:click="clearSelectedOrder" class="text-xs bg-white text-indigo-600 hover:text-white hover:bg-indigo-600 px-3 py-1.5 rounded-lg border border-indigo-200 font-bold transition shadow-sm">
                        Batal
                    </button>
                </div>
            @endif

        <div class="p-4 sm:p-5 space-y-2 border-b border-slate-100 bg-slate-50/50">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nama Pelanggan</label>
                    <input wire:model="customer_name" type="text" placeholder="Masukkan nama..."
                        class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    @error('customer_name') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Meja</label>
                    <input wire:model="table_number" type="number" placeholder="Contoh: 12"
                        class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    @error('table_number') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Catatan Tambahan</label>
                <textarea wire:model="note" placeholder="Contoh: Ekstra pedas, pisah es..."
                    class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm resize-none h-14"></textarea>
                @error('note') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="h-[400px] lg:h-[500px] overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50">
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
                                    :class="isItemCooked(item.id) ? 'opacity-50 cursor-not-allowed' : ''"
                                    :disabled="isItemCooked(item.id)"
                                    class="w-8 h-8 flex items-center justify-center bg-white rounded shadow-sm transition"
                                    :class="isItemCooked(item.id) ? '' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-50'">-</button>
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
            <button @click="validateAndShowModal()"
                class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span x-text="$wire.get('selected_order_id') ? 'Simpan Perubahan Pesanan' : 'Kirim Pesanan ke Dapur'"></span>
            </button>
        </div>
    </div>

    <!-- Modal Konfirmasi Kirim Pesanan -->
    <div x-show="showConfirmModal" x-cloak style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div @click.outside="showConfirmModal = false" class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-indigo-50">
                <h4 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Konfirmasi Pesanan
                </h4>
                <button @click="showConfirmModal = false" class="text-slate-400 hover:text-rose-500 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <p class="font-bold text-slate-700 mb-2">Meja: <span x-text="$wire.get('table_number') || 'Take Away'"></span></p>
                    <p class="font-bold text-slate-700 mb-4">Pelanggan: <span x-text="$wire.get('customer_name') || '-'"></span></p>
                    <div class="bg-slate-50 rounded-xl p-4 max-h-[200px] overflow-y-auto">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Item Pesanan:</p>
                        <template x-for="item in cartItems" :key="item.id">
                            <div class="flex justify-between text-sm py-1 border-b border-slate-100 last:border-0">
                                <span class="font-medium" x-text="item.quantity + 'x ' + item.name"></span>
                                <span class="font-bold text-indigo-600" x-text="formatRupiah(item.price * item.quantity)"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex justify-between items-center mb-4 pt-3 border-t border-slate-100">
                    <span class="font-bold text-slate-700">Total:</span>
                    <span class="text-xl font-extrabold text-indigo-600" x-text="formatRupiah(totalPrice)"></span>
                </div>
                <div x-show="$wire.get('note')" class="bg-yellow-50 text-yellow-800 text-sm p-3 rounded-lg border border-yellow-200 mb-4">
                    <strong>Catatan:</strong> <span x-text="$wire.get('note')"></span>
                </div>
                <div class="flex gap-3">
                    <button @click="showConfirmModal = false" class="flex-1 bg-slate-200 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-300 transition">
                        Batal
                    </button>
                    <button @click="submitOrder(); showConfirmModal = false" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Konfirmasi
                    </button>
                </div>
            </div>
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
                showConfirmModal: false,
                
                init() {
                    this.$watch('cart', value => {
                        // this ensures reactivity if needed
                    });
                },

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
                isItemCooked(itemId) {
                    const cookedItems = this.$wire.get('selectedOrderCookedItems') || [];
                    return cookedItems.includes(itemId);
                },
                get totalPrice() {
                    return this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },
                validateAndShowModal() {
                    const customerName = this.$wire.get('customer_name');
                    const tableNumber = this.$wire.get('table_number');
                    
                    if (!customerName || !customerName.trim()) {
                        this.showNotification('Nama pelanggan wajib diisi!', 'error');
                        return;
                    }
                    if (!tableNumber || !tableNumber.toString().trim()) {
                        this.showNotification('Nomor meja wajib diisi!', 'error');
                        return;
                    }
                    this.showConfirmModal = true;
                },
                submitOrder() {
                    if (this.cartItems.length === 0 && !this.$wire.get('selected_order_id')) {
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