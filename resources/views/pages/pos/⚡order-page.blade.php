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
            ->whereIn('kitchen_status', ['waiting', 'ready', 'rejected'])
            ->orderByRaw("FIELD(kitchen_status, 'ready', 'rejected', 'waiting')")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function selectActiveOrder($orderId)
    {
        $order = auth()->user()->branch->orders()->with('items.product')->findOrFail($orderId);
        $this->selected_order_id = $order->id;
        $this->customer_name = $order->customer_name;
        $this->table_number = $order->table_number;

        // Clear cart — add-to-order mode only adds NEW items
        $this->dispatch('cart-cleared');
    }

    public function clearSelectedOrder()
    {
        $this->selected_order_id = null;
        $this->customer_name = '';
        $this->table_number = '';
        $this->dispatch('cart-cleared');
    }

    public function updateOrderInfo($orderId, $customerName, $tableNumber)
    {
        $order = auth()->user()->branch->orders()->findOrFail($orderId);

        $order->update([
            'customer_name' => $customerName,
            'table_number' => $tableNumber,
        ]);

        $this->dispatch('custom-notify', message: 'Info pesanan diperbarui!', type: 'success');
    }

    public function markItemAsServed($itemId)
    {
        $item = OrderItem::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->where('kitchen_status', 'ready')->findOrFail($itemId);

        $item->update(['kitchen_status' => 'served']);
        $this->syncOrderKitchenStatus($item->order);
        $this->dispatch('custom-notify', message: 'Item sudah dihidangkan!', type: 'success');
    }

    public function markAllAsServed($orderId)
    {
        $order = Order::where('branch_id', auth()->user()->branch_id)->findOrFail($orderId);
        $order->items()->where('kitchen_status', 'ready')->update(['kitchen_status' => 'served']);
        $this->syncOrderKitchenStatus($order);
        $this->dispatch('custom-notify', message: 'Semua item sudah dihidangkan!', type: 'success');
    }

    private function syncOrderKitchenStatus(Order $order)
    {
        $order->refresh();
        $statuses = $order->items()->pluck('kitchen_status')->unique();

        // Exclude fully terminal statuses for determining order status
        $activeStatuses = $statuses->diff(['served', 'rejected']);

        if ($activeStatuses->contains('waiting')) {
            $order->update(['kitchen_status' => 'waiting']);
        } elseif ($activeStatuses->contains('ready')) {
            $order->update(['kitchen_status' => 'ready']);
        } elseif ($statuses->contains('rejected') && !$statuses->contains('served')) {
            // All items rejected, none served
            $order->update(['kitchen_status' => 'rejected']);
        } else {
            $order->update(['kitchen_status' => 'served']);
        }
    }

    public function submitOrderAlpine($cartData)
    {
        if (empty($cartData) && ! $this->selected_order_id) {
            $this->dispatch('custom-notify', message: 'Pilih minimal satu menu dulu!', type: 'error');

            return;
        }

        if (empty($cartData)) {
            $this->dispatch('custom-notify', message: 'Tambahkan minimal satu item baru!', type: 'error');

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
        $validProducts = auth()->user()->branch->products()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $totalPrice = 0;
        $validOrderItems = [];

        // 3. Looping data cart dari frontend, tapi bandingkan dengan data valid dari database
        foreach ($cartData as $item) {
            $productId = $item['id'];

            if (! $validProducts->has($productId)) {
                continue;
            }

            $product = $validProducts[$productId];
            $quantity = (int) $item['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            // AMBIL HARGA DARI DATABASE, BUKAN DARI FRONTEND
            $totalPrice += ($product->price * $quantity);

            $validOrderItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
                'note' => $item['note'] ?? null,
            ];
        }

        if (empty($validOrderItems)) {
            $this->dispatch('custom-notify', message: 'Produk tidak valid!', type: 'error');

            return;
        }

        if ($this->selected_order_id) {
            // ADD TO EXISTING ORDER — append only, no modify/delete
            $order = auth()->user()->branch->orders()->find($this->selected_order_id);
            if (! $order) {
                $this->dispatch('custom-notify', message: 'Order tidak ditemukan!', type: 'error');

                return;
            }

            // Add new items
            foreach ($validOrderItems as $itemData) {
                $itemData['order_id'] = $order->id;
                $itemData['kitchen_status'] = 'waiting';
                OrderItem::create($itemData);
            }

            // Update total price
            $order->total_price += $totalPrice;

            // If order was served/ready, set back to waiting since new items added
            if (in_array($order->kitchen_status, ['ready', 'served'])) {
                $order->kitchen_status = 'waiting';
            }
            $order->save();

            $this->dispatch('custom-notify', message: 'Item baru berhasil ditambahkan ke pesanan!', type: 'success');
        } else {
            // BUAT ORDER BARU
            $order = auth()->user()->branch->orders()->create([
                'order_number' => 'ORD-'.now()->format('YmdHis'),
                'username_cashier' => auth()->user()->name ?? 'System',
                'customer_name' => $this->customer_name,
                'table_number' => $this->table_number,
                'total_price' => $totalPrice,
                'status' => 'unpaid',
                'kitchen_status' => 'waiting',
            ]);

            foreach ($validOrderItems as $itemData) {
                $itemData['order_id'] = $order->id;
                $itemData['kitchen_status'] = 'waiting';
                OrderItem::create($itemData);
            }
            $this->dispatch('custom-notify', message: 'Pesanan baru berhasil dikirim ke dapur!', type: 'success');
        }

        $this->reset(['cart', 'customer_name', 'table_number', 'search', 'selected_order_id']);
        $this->dispatch('cart-cleared');
    }
};
?>

<div class="flex flex-col lg:flex-row min-h-screen lg:min-h-screen bg-slate-50 font-sans relative lg:overflow-hidden"
    x-data="orderCart()" @cart-cleared.window="cart = {}"
    @custom-notify.window="showNotification($event.detail.message, $event.detail.type)">

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
                        @php
                            $readyCount = $activeOrder->items->where('kitchen_status', 'ready')->count();
                            $servedCount = $activeOrder->items->where('kitchen_status', 'served')->count();
                            $waitingCount = $activeOrder->items->where('kitchen_status', 'waiting')->count();
                            $rejectedCount = $activeOrder->items->where('kitchen_status', 'rejected')->count();
                            $totalItems = $activeOrder->items->count();
                        @endphp
                        <div wire:key="active-order-{{ $activeOrder->id }}" x-data="{ showDetails: false }" class="min-w-[240px] bg-white p-3 rounded-xl shadow-sm border border-slate-200 flex-shrink-0 flex flex-col gap-1.5 relative">
                            <div class="flex justify-between items-center gap-2">
                                <p class="font-bold text-slate-800 text-sm truncate">{{ $activeOrder->table_number ? 'Meja ' . $activeOrder->table_number : 'TA' }} • {{ $activeOrder->customer_name }}</p>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if($readyCount > 0)<span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-1.5 py-0.5 rounded animate-pulse">{{ $readyCount }} Siap</span>@endif
                                    @if($rejectedCount > 0)<span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded">{{ $rejectedCount }} Ditolak</span>@endif
                                    <span class="bg-indigo-50 text-indigo-600 text-[10px] font-bold px-1.5 py-0.5 rounded">{{ $totalItems }}</span>
                                </div>
                            </div>
                            <div class="flex w-full h-1.5 rounded-full overflow-hidden bg-slate-100">
                                @if($servedCount > 0)<div class="bg-emerald-500" style="width:{{ ($servedCount/$totalItems)*100 }}%"></div>@endif
                                @if($readyCount > 0)<div class="bg-amber-400" style="width:{{ ($readyCount/$totalItems)*100 }}%"></div>@endif
                                @if($waitingCount > 0)<div class="bg-slate-300" style="width:{{ ($waitingCount/$totalItems)*100 }}%"></div>@endif
                                @if($rejectedCount > 0)<div class="bg-rose-400" style="width:{{ ($rejectedCount/$totalItems)*100 }}%"></div>@endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="showDetails = true" class="text-[11px] text-indigo-600 font-bold hover:text-indigo-800 underline">Detail</button>
                                @if($activeOrder->status === 'unpaid')
                                    <span class="text-slate-300">|</span>
                                    <button type="button" wire:click="selectActiveOrder({{ $activeOrder->id }})" class="text-[11px] text-emerald-600 font-bold hover:text-emerald-800 underline">+ Tambah</button>
                                @endif
                                @if($readyCount > 0)
                                    <button wire:click="markAllAsServed({{ $activeOrder->id }})" class="ml-auto text-[11px] bg-emerald-500 text-white hover:bg-emerald-600 px-2 py-1 rounded-lg font-bold transition">Hidangkan ({{ $readyCount }})</button>
                                @elseif($activeOrder->kitchen_status === 'waiting')
                                    <span class="ml-auto text-[10px] text-slate-400 font-medium">⏳ Menunggu</span>
                                @endif
                            </div>

                            <!-- Modal Detail -->
                            <div x-show="showDetails" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
                                <div @click.outside="showDetails = false" class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative" x-transition>
                                    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                        <h4 class="font-bold text-slate-800 text-lg">Detail Pesanan</h4>
                                        <button @click="showDetails = false" class="text-slate-400 hover:text-rose-500 transition">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                    <div class="p-4 max-h-[60vh] overflow-y-auto">
                                        <div x-data="{ editing: false, editName: '{{ addslashes($activeOrder->customer_name) }}', editTable: '{{ addslashes($activeOrder->table_number) }}' }" class="mb-4 bg-indigo-50 text-indigo-800 p-3 rounded-xl border border-indigo-100">
                                            <template x-if="!editing">
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <span class="font-black block text-base">{{ $activeOrder->table_number ? 'Meja ' . $activeOrder->table_number : 'Take Away' }}</span>
                                                        <span class="text-sm font-medium">Atas Nama: {{ $activeOrder->customer_name }}</span>
                                                    </div>
                                                    <button @click="editing = true" class="text-indigo-500 hover:text-indigo-700 p-1.5 rounded-lg hover:bg-indigo-100" title="Edit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    </button>
                                                </div>
                                            </template>
                                            <template x-if="editing">
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="text-[10px] font-bold text-indigo-600 uppercase">Nama Customer</label>
                                                        <input type="text" x-model="editName" class="w-full text-sm border border-indigo-200 rounded-lg px-2.5 py-1.5 bg-white focus:ring-2 focus:ring-indigo-400" />
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-bold text-indigo-600 uppercase">No. Meja</label>
                                                        <input type="text" x-model="editTable" class="w-full text-sm border border-indigo-200 rounded-lg px-2.5 py-1.5 bg-white focus:ring-2 focus:ring-indigo-400" />
                                                    </div>
                                                    <div class="flex gap-2 pt-1">
                                                        <button @click="$wire.updateOrderInfo({{ $activeOrder->id }}, editName, editTable); editing = false" class="flex-1 text-xs bg-indigo-600 text-white py-1.5 rounded-lg font-bold hover:bg-indigo-700 transition">Simpan</button>
                                                        <button @click="editing = false; editName = '{{ addslashes($activeOrder->customer_name) }}'; editTable = '{{ addslashes($activeOrder->table_number) }}'" class="flex-1 text-xs bg-slate-200 text-slate-700 py-1.5 rounded-lg font-bold hover:bg-slate-300 transition">Batal</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <ul class="text-sm text-slate-600 space-y-3">
                                            @foreach($activeOrder->items as $item)
                                                @php
                                                    $statusConfig = match($item->kitchen_status) {
                                                        'waiting' => ['label' => 'Menunggu', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'icon' => '⏳'],
                                                        'ready' => ['label' => 'Siap', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => '✅'],
                                                        'served' => ['label' => 'Dihidangkan', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => '🍽️'],
                                                        'rejected' => ['label' => 'Ditolak', 'bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'icon' => '❌'],
                                                        default => ['label' => ucfirst($item->kitchen_status), 'bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'icon' => '❓'],
                                                    };
                                                @endphp
                                                <li class="border border-slate-100 rounded-xl p-3 {{ $item->kitchen_status === 'rejected' ? 'opacity-60' : '' }}">
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex gap-2 items-start">
                                                            <span class="font-black text-indigo-700 bg-indigo-100 px-2.5 py-1 rounded-lg text-sm shadow-sm">{{ $item->quantity }}x</span>
                                                            <div>
                                                                <p class="font-bold text-slate-800 {{ $item->kitchen_status === 'rejected' ? 'line-through' : '' }}">{{ $item->product_name ?? ($item->product ? $item->product->name : 'Item') }}</p>
                                                                @if($item->note)<p class="text-xs text-slate-500 mt-0.5 italic">📝 {{ $item->note }}</p>@endif
                                                                @if($item->reject_reason)<p class="text-xs text-rose-600 mt-0.5 font-medium">Alasan: {{ $item->reject_reason }}</p>@endif
                                                            </div>
                                                        </div>
                                                        <span class="text-[10px] font-bold px-2 py-1 rounded-lg {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} whitespace-nowrap">{{ $statusConfig['icon'] }} {{ $statusConfig['label'] }}</span>
                                                    </div>
                                                    @if($item->kitchen_status === 'ready')
                                                        <div class="mt-2 flex justify-end">
                                                            <button wire:click="markItemAsServed({{ $item->id }})" class="text-xs bg-emerald-500 text-white hover:bg-emerald-600 px-3 py-1.5 rounded-lg font-bold transition flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                                Sudah Dihidangkan
                                                            </button>
                                                        </div>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @if($readyCount > 0)
                                    <div class="p-4 border-t border-slate-100 bg-slate-50">
                                        <button wire:click="markAllAsServed({{ $activeOrder->id }})" class="w-full bg-emerald-500 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-600 transition flex items-center justify-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Hidangkan Semua ({{ $readyCount }})
                                        </button>
                                    </div>
                                    @endif
                                </div>
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
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
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
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-3 rounded-xl flex justify-between items-center shadow-sm">
                    <div>
                        <span class="text-[10px] font-black block uppercase tracking-wider text-emerald-500 mb-0.5">Mode Tambah Item</span>
                        <span class="font-bold text-sm">Menambah ke {{ $table_number ? 'Meja ' . $table_number : 'Take Away' }}</span>
                    </div>
                    <button wire:click="clearSelectedOrder" class="text-xs bg-white text-emerald-600 hover:text-white hover:bg-emerald-600 px-3 py-1.5 rounded-lg border border-emerald-200 font-bold transition shadow-sm">
                        Batal
                    </button>
                </div>
            @endif

        <div class="p-4 sm:p-5 space-y-2 border-b border-slate-100 bg-slate-50/50">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nama Pelanggan</label>
                    <input wire:model="customer_name" type="text" placeholder="Masukkan nama..."
                        class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm {{ $selected_order_id ? 'bg-slate-100 cursor-not-allowed' : '' }}"
                        {{ $selected_order_id ? 'readonly' : '' }}>
                    @error('customer_name') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Meja</label>
                    <input wire:model="table_number" type="number" placeholder="Contoh: 12"
                        class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm {{ $selected_order_id ? 'bg-slate-100 cursor-not-allowed' : '' }}"
                        {{ $selected_order_id ? 'readonly' : '' }}>
                    @error('table_number') <span class="text-rose-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
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

                        <!-- Per-item note input -->
                        <div class="mb-2">
                            <input type="text" x-model="item.note" placeholder="Catatan: ekstra pedas, tanpa gula..."
                                class="w-full p-2 rounded-lg border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm placeholder-slate-400 transition">
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
                    <p class="text-center font-medium" x-text="$wire.get('selected_order_id') ? 'Tambahkan item baru ke pesanan ini' : 'Keranjang masih kosong'"></p>
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
                <span x-text="$wire.get('selected_order_id') ? 'Tambah Item ke Pesanan' : 'Kirim Pesanan ke Dapur'"></span>
            </button>
        </div>
    </div>

    <!-- Modal Konfirmasi Kirim Pesanan -->
    <div x-show="showConfirmModal" x-cloak style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div @click.outside="showConfirmModal = false" class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-indigo-50">
                <h4 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span x-text="$wire.get('selected_order_id') ? 'Konfirmasi Tambah Item' : 'Konfirmasi Pesanan'"></span>
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
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2" x-text="$wire.get('selected_order_id') ? 'Item Baru yang Ditambahkan:' : 'Item Pesanan:'"></p>
                        <template x-for="item in cartItems" :key="item.id">
                            <div class="flex flex-col py-1 border-b border-slate-100 last:border-0">
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium" x-text="item.quantity + 'x ' + item.name"></span>
                                    <span class="font-bold text-indigo-600" x-text="formatRupiah(item.price * item.quantity)"></span>
                                </div>
                                <template x-if="item.note && item.note.trim()">
                                    <span class="text-xs text-slate-500 italic mt-0.5" x-text="'📝 ' + item.note"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex justify-between items-center mb-4 pt-3 border-t border-slate-100">
                    <span class="font-bold text-slate-700">Total:</span>
                    <span class="text-xl font-extrabold text-indigo-600" x-text="formatRupiah(totalPrice)"></span>
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
                        this.cart[product.id] = { ...product, quantity: 1, note: '' };
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
                    if (this.cartItems.length === 0) {
                        this.showNotification('Tambahkan minimal satu item!', 'error');
                        return;
                    }
                    this.showConfirmModal = true;
                },
                submitOrder() {
                    if (this.cartItems.length === 0) {
                        alert('Pilih minimal satu menu dulu!');
                        return;
                    }
                    // Include per-item notes in submission
                    const items = this.cartItems.map(item => ({
                        id: item.id,
                        quantity: item.quantity,
                        price: item.price,
                        note: item.note || ''
                    }));
                    this.$wire.submitOrderAlpine(items);
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