<?php

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount()
    {
        if (auth()->user()->role !== 'waiter') {
            abort(403, 'Akses Ditolak: Hanya Waiter yang berhak mengakses halaman ini.');
        }
    }

    #[Computed]
    public function activeOrders()
    {
        return auth()->user()->branch->orders()->with('items.product')
            ->whereIn('kitchen_status', ['waiting', 'ready', 'rejected', 'served'])
            ->where('status', '!=', 'paid')
            ->orderByRaw("FIELD(kitchen_status, 'ready', 'rejected', 'waiting', 'served')")
            ->orderBy('created_at', 'desc')
            ->get();
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

    public function updateOrderInfo($orderId, $customerName, $tableNumber)
    {
        $order = Order::where('branch_id', auth()->user()->branch_id)->findOrFail($orderId);
        $order->update([
            'customer_name' => $customerName,
            'table_number' => $tableNumber,
        ]);
        $this->dispatch('custom-notify', message: 'Info pesanan diperbarui!', type: 'success');
    }

    private function syncOrderKitchenStatus(Order $order)
    {
        $order->refresh();
        $statuses = $order->items()->pluck('kitchen_status')->unique();
        $activeStatuses = $statuses->diff(['served', 'rejected']);

        if ($activeStatuses->contains('waiting')) {
            $order->update(['kitchen_status' => 'waiting']);
        } elseif ($activeStatuses->contains('ready')) {
            $order->update(['kitchen_status' => 'ready']);
        } elseif ($statuses->contains('rejected') && ! $statuses->contains('served')) {
            $order->update(['kitchen_status' => 'rejected']);
        } else {
            $order->update(['kitchen_status' => 'served']);
        }
    }
};
?>
<div class="min-h-screen bg-slate-50 font-sans"
    x-data="{ showToast: false, toastMessage: '', toastType: 'success', toastTimeout: null, editOrderId: null, editCustomerName: '', editTableNumber: '' }"
    @custom-notify.window="toastMessage = $event.detail.message; toastType = $event.detail.type; showToast = true; if(toastTimeout) clearTimeout(toastTimeout); toastTimeout = setTimeout(() => showToast = false, 3500);"
    @edit-order.window="editOrderId = $event.detail.id; editCustomerName = $event.detail.customer_name; editTableNumber = $event.detail.table_number"
    @close-edit.window="editOrderId = null">

    <div x-cloak x-show="showToast" 
         x-transition
         class="fixed top-8 right-8 text-white px-6 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-4 ring-4"
         :class="toastType === 'success' ? 'bg-emerald-600 ring-emerald-500/30' : 'bg-rose-600 ring-rose-500/30'">
        <div class="flex flex-col">
            <span class="font-black text-lg leading-none mb-1" x-text="toastType === 'success' ? 'Berhasil!' : 'Gagal!'"></span>
            <span class="font-medium text-white/90 text-sm" x-text="toastMessage"></span>
        </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 sm:mb-8">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Status Pesanan</h2>
        </div>

        <div wire:poll.5s.visible>
            @if(count($this->activeOrders) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($this->activeOrders as $activeOrder)
                        @php
                            $readyCount = $activeOrder->items->where('kitchen_status', 'ready')->count();
                            $servedCount = $activeOrder->items->where('kitchen_status', 'served')->count();
                            $waitingCount = $activeOrder->items->where('kitchen_status', 'waiting')->count();
                            $rejectedCount = $activeOrder->items->where('kitchen_status', 'rejected')->count();
                            $totalItems = $activeOrder->items->count();
                        @endphp
                        <div wire:key="active-order-{{ $activeOrder->id }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                            <div class="p-4 border-b border-slate-100 bg-slate-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-slate-800 text-lg">{{ $activeOrder->table_number ? 'Meja ' . $activeOrder->table_number : 'Take Away' }}</p>
                                        <p class="text-sm font-medium text-slate-500">{{ $activeOrder->customer_name }}</p>
                                    </div>
                                    <button @click="$dispatch('edit-order', { id: {{ $activeOrder->id }}, customer_name: '{{ $activeOrder->customer_name }}', table_number: '{{ $activeOrder->table_number }}' })" class="text-slate-400 hover:text-indigo-600 transition p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <a href="{{ route('order', ['order_id' => $activeOrder->id]) }}" class="flex-1 bg-indigo-500 text-white text-sm py-2 px-3 rounded-lg font-bold hover:bg-indigo-600 transition flex items-center justify-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Tambah Item
                                    </a>
                                </div>
                                <div class="flex w-full h-2 rounded-full overflow-hidden bg-slate-100 mt-3">
                                    @if($servedCount > 0)<div class="bg-emerald-500" style="width:{{ ($servedCount/$totalItems)*100 }}%"></div>@endif
                                    @if($readyCount > 0)<div class="bg-amber-400" style="width:{{ ($readyCount/$totalItems)*100 }}%"></div>@endif
                                    @if($waitingCount > 0)<div class="bg-slate-300" style="width:{{ ($waitingCount/$totalItems)*100 }}%"></div>@endif
                                    @if($rejectedCount > 0)<div class="bg-rose-400" style="width:{{ ($rejectedCount/$totalItems)*100 }}%"></div>@endif
                                </div>
                            </div>

                            <div class="p-4 flex-1 max-h-[300px] overflow-y-auto">
                                <ul class="space-y-2">
                                    @foreach($activeOrder->items as $item)
                                        @php
                                            $statusConfig = match($item->kitchen_status) {
                                                'waiting' => ['label' => 'Menunggu', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
                                                'ready' => ['label' => 'Siap', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
                                                'served' => ['label' => 'Dihidangkan', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
                                                'rejected' => ['label' => 'Ditolak', 'bg' => 'bg-rose-100', 'text' => 'text-rose-700'],
                                                default => ['label' => ucfirst($item->kitchen_status), 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
                                            };
                                        @endphp
                                        <li class="flex justify-between items-center p-2 rounded-lg border border-slate-100 {{ $item->kitchen_status === 'rejected' ? 'opacity-60' : '' }}">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-indigo-700 bg-indigo-100 px-2 py-1 rounded text-sm">{{ $item->quantity }}x</span>
                                                <div>
                                                    <p class="font-medium text-slate-800 {{ $item->kitchen_status === 'rejected' ? 'line-through' : '' }}">{{ $item->product_name ?? ($item->product ? $item->product->name : 'Item') }}</p>
                                                    @if($item->note)<p class="text-xs text-slate-500 italic">📝 {{ $item->note }}</p>@endif
                                                    @if($item->reject_reason)<p class="text-xs text-rose-600">Alasan: {{ $item->reject_reason }}</p>@endif
                                                </div>
                                            </div>
                                            @if($item->kitchen_status === 'ready')
                                                <button wire:click="markItemAsServed({{ $item->id }})" class="text-sm bg-emerald-500 text-white hover:bg-emerald-600 px-3 py-1.5 rounded-lg font-bold transition">
                                                    Hidangkan
                                                </button>
                                            @else
                                                <span class="text-xs font-bold px-2 py-1 rounded {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                                                    {{ $statusConfig['label'] }}
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            @if($readyCount > 0)
                                <div class="p-4 border-t border-slate-100 bg-slate-50">
                                    <button wire:click="markAllAsServed({{ $activeOrder->id }})" class="w-full bg-emerald-500 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-600 transition flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Hidangkan Semua ({{ $readyCount }})
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="w-20 h-20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="text-xl font-medium">Tidak ada pesanan aktif</p>
                    <p class="text-sm mt-1">Pesanan akan muncul di sini setelah dibuat</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Edit Order -->
    <div x-show="editOrderId" x-cloak style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div @click.outside="$dispatch('close-edit')" class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-indigo-50">
                <h4 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Edit Pesanan
                </h4>
                <button @click="$dispatch('close-edit')" class="text-slate-400 hover:text-rose-500 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-5">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Pelanggan</label>
                        <input x-model="editCustomerName" type="text" class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Meja</label>
                        <input x-model="editTableNumber" type="text" class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button @click="$dispatch('close-edit')" class="flex-1 bg-slate-200 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-300 transition">
                        Batal
                    </button>
                    <button @click="$wire.updateOrderInfo(editOrderId, editCustomerName, editTableNumber); $dispatch('close-edit')" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>