<?php

use App\Models\KitchenWaste;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Component;

new class extends Component
{
    public $rejectItemId = null;
    public $rejectReason = '';
    public $rejectType = ''; // 'rejected' or 'waste'

    public function getOrdersProperty()
    {
        return Order::with('items.product')
            ->where('branch_id', auth()->user()->branch_id)
            ->whereHas('items', function ($query) {
                $query->where('kitchen_status', 'waiting');
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function changeItemStatus($itemId, $newStatus)
    {
        $item = OrderItem::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->findOrFail($itemId);

        $item->update(['kitchen_status' => $newStatus]);
        $this->syncOrderKitchenStatus($item->order);

        $statusLabels = [
            'ready' => 'Masakan siap!',
        ];

        $this->dispatch('custom-notify', message: $statusLabels[$newStatus] ?? 'Status diperbarui!', type: 'success');
    }

    public function markAllReady($orderId)
    {
        $order = Order::where('branch_id', auth()->user()->branch_id)->findOrFail($orderId);
        $order->items()->where('kitchen_status', 'waiting')->update(['kitchen_status' => 'ready']);
        $this->syncOrderKitchenStatus($order);
        $this->dispatch('custom-notify', message: 'Semua item siap diantar!', type: 'success');
    }

    public function openRejectModal($itemId, $type)
    {
        $this->rejectItemId = $itemId;
        $this->rejectType = $type;
        $this->rejectReason = '';
    }

    public function closeRejectModal()
    {
        $this->rejectItemId = null;
        $this->rejectType = '';
        $this->rejectReason = '';
    }

    public function confirmReject()
    {
        if (empty(trim($this->rejectReason))) {
            $this->dispatch('custom-notify', message: 'Alasan wajib diisi!', type: 'error');
            return;
        }

        $item = OrderItem::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->findOrFail($this->rejectItemId);

        if ($this->rejectType === 'waste') {
            // Waste: save to kitchen_wastes table, item stays in current status
            KitchenWaste::create([
                'branch_id' => auth()->user()->branch_id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'reason' => $this->rejectReason,
                'chef_name' => auth()->user()->name,
            ]);

            $this->dispatch('custom-notify', message: 'Gagal masak dicatat! Item tetap bisa dimasak ulang.', type: 'success');
        } else {
            // Reject: change item status to rejected
            $item->update([
                'kitchen_status' => 'rejected',
                'reject_reason' => $this->rejectReason,
            ]);

            $this->syncOrderKitchenStatus($item->order);
            $this->dispatch('custom-notify', message: 'Item ditolak!', type: 'success');
        }

        $this->closeRejectModal();
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
        } elseif ($statuses->contains('rejected') && !$statuses->contains('served')) {
            // All items rejected, none served
            $order->update(['kitchen_status' => 'rejected']);
        } else {
            $order->update(['kitchen_status' => 'served']);
        }
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kitchen Display System') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-slate-50 min-h-screen" 
         x-data="{ showToast: false, toastMessage: '', toastType: 'success', toastTimeout: null }"
         @custom-notify.window="
            toastMessage = $event.detail.message; 
            toastType = $event.detail.type; 
            showToast = true;
            if(toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => showToast = false, 3500);
         ">
        
        <div x-cloak x-show="showToast" 
             x-transition
             class="fixed top-8 right-8 text-white px-6 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-4 ring-4"
             :class="toastType === 'success' ? 'bg-emerald-600 ring-emerald-500/30' : 'bg-rose-600 ring-rose-500/30'">
            <div class="flex flex-col">
                <span class="font-black text-lg leading-none mb-1" x-text="toastType === 'success' ? 'Berhasil!' : 'Gagal!'"></span>
                <span class="font-medium text-white/90 text-sm" x-text="toastMessage"></span>
            </div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- wire:poll refreshes the component every 5 seconds securely -->
            <div wire:poll.5s class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->orders as $order)
                    @php
                        $waitingItems = $order->items->where('kitchen_status', 'waiting');
                        $readyItems = $order->items->where('kitchen_status', 'ready');
                        $rejectedItems = $order->items->where('kitchen_status', 'rejected');
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-start bg-slate-50">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-xl">{{ $order->table_number ? 'Meja ' . $order->table_number : 'Take Away' }}</h3>
                                <p class="text-sm font-medium text-slate-500">{{ $order->customer_name }} • {{ $order->order_number }}</p>
                            </div>
                            <div>
                                <div class="flex flex-wrap gap-1 justify-end">
                                    @if($waitingItems->count() > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold uppercase">{{ $waitingItems->count() }} Menunggu</span>
                                    @endif

                                    @if($readyItems->count() > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 text-[10px] font-bold uppercase">{{ $readyItems->count() }} Siap</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400 mt-2 text-right">
                                    {{ $order->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-5 flex-1 bg-white">
                            <ul class="space-y-3">
                                @foreach($order->items->where('kitchen_status', 'waiting') as $item)
                                    @php
                                        $isWaiting = $item->kitchen_status === 'waiting';
                                        $isReady = $item->kitchen_status === 'ready';
                                        $isRejected = $item->kitchen_status === 'rejected';
                                        $isTerminal = in_array($item->kitchen_status, ['ready', 'served', 'rejected']);
                                    @endphp
                                    <li class="flex flex-col rounded-xl p-3 border transition-all
                                        {{ $isWaiting ? 'border-slate-200 bg-slate-50' : '' }}
                                        {{ $isReady ? 'border-emerald-200 bg-emerald-50 opacity-50' : '' }}
                                        {{ $isRejected ? 'border-rose-200 bg-rose-50 opacity-40' : '' }}
                                    ">
                                        <div class="flex justify-between items-start">
                                            <div class="flex gap-3">
                                                <span class="font-black w-8 h-8 flex items-center justify-center rounded-lg text-sm
                                                    {{ $isWaiting ? 'text-indigo-600 bg-indigo-50' : '' }}
                                                    {{ $isReady ? 'text-emerald-600 bg-emerald-100' : '' }}
                                                    {{ $isRejected ? 'text-rose-600 bg-rose-100' : '' }}
                                                ">{{ $item->quantity }}x</span>
                                                <div>
                                                    <p class="font-bold {{ $isRejected ? 'text-slate-500 line-through' : 'text-slate-800' }}">
                                                        {{ $item->product_name ?? ($item->product ? $item->product->name : 'Menu Dihapus') }}
                                                    </p>
                                                    @if($item->note)
                                                        <p class="text-xs text-slate-500 italic mt-0.5">📝 {{ $item->note }}</p>
                                                    @endif
                                                    @if($isReady)
                                                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">✅ Siap Diantar</span>
                                                    @endif
                                                    @if($isRejected)
                                                        <span class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">❌ Ditolak</span>
                                                        @if($item->reject_reason)
                                                            <p class="text-xs text-rose-500 mt-0.5">Alasan: {{ $item->reject_reason }}</p>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                @if($isWaiting)
                                                    <div class="flex flex-col gap-2 mt-2">
                                                        <button wire:click="changeItemStatus({{ $item->id }}, 'ready')" class="w-full text-sm bg-emerald-500 text-white hover:bg-emerald-600 px-3 py-2 rounded-lg font-bold transition" title="Tandai Siap">
                                                            ✅ Siap
                                                        </button>
                                                        <div class="flex gap-1.5">
                                                            <button wire:click="openRejectModal({{ $item->id }}, 'rejected')" class="flex-1 text-[10px] bg-rose-50 text-rose-500 hover:bg-rose-100 hover:text-rose-700 px-2 py-1.5 rounded-lg font-bold transition border border-rose-200" title="Tolak">
                                                                ❌ Tolak
                                                            </button>
                                                            <button wire:click="openRejectModal({{ $item->id }}, 'waste')" class="flex-1 text-[10px] bg-orange-50 text-orange-500 hover:bg-orange-100 hover:text-orange-700 px-2 py-1.5 rounded-lg font-bold transition border border-orange-200" title="Gagal Masak">
                                                                ⚠️ Gagal
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="p-5 border-t border-slate-100 bg-slate-50 mt-auto flex gap-2">
                            @if($waitingItems->count() > 0)
                                <button wire:click="markAllReady({{ $order->id }})" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-4 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                    ✅ Semua Siap ({{ $waitingItems->count() }})
                                </button>
                            @else
                                <div class="flex-1 text-center text-sm font-bold text-emerald-600 py-3">
                                    Semua item sudah selesai dimasak
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center py-24 bg-white rounded-3xl border border-slate-200 border-dashed shadow-sm">
                        <svg class="w-20 h-20 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <h3 class="text-xl font-bold text-slate-600 mb-1">Dapur Kosong</h3>
                        <p class="text-slate-400 font-medium">Belum ada pesanan yang masuk.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Reject/Waste Modal -->
        @if($rejectItemId)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center {{ $rejectType === 'rejected' ? 'bg-rose-50' : 'bg-orange-50' }}">
                    <h4 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                        @if($rejectType === 'rejected')
                            ❌ Tolak Pesanan
                        @else
                            ⚠️ Gagal Masak
                        @endif
                    </h4>
                    <button wire:click="closeRejectModal" class="text-slate-400 hover:text-rose-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-5">
                    <label class="block text-sm font-bold text-slate-700 mb-2">
                        @if($rejectType === 'rejected')
                            Alasan Penolakan <span class="text-rose-500">*</span>
                        @else
                            Alasan Gagal Masak <span class="text-rose-500">*</span>
                        @endif
                    </label>
                    <textarea wire:model="rejectReason" 
                        placeholder="{{ $rejectType === 'rejected' ? 'Contoh: Bahan habis, stok kosong...' : 'Contoh: Gosong, tumpah, bahan rusak...' }}"
                        class="w-full p-3 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm resize-none h-24"
                    ></textarea>
                    <div class="flex gap-3 mt-4">
                        <button wire:click="closeRejectModal" class="flex-1 bg-slate-200 text-slate-700 py-3 rounded-xl font-bold hover:bg-slate-300 transition">
                            Batal
                        </button>
                        <button wire:click="confirmReject" class="flex-1 py-3 rounded-xl font-bold transition flex items-center justify-center gap-2 text-white
                            {{ $rejectType === 'rejected' ? 'bg-rose-500 hover:bg-rose-600' : 'bg-orange-500 hover:bg-orange-600' }}">
                            @if($rejectType === 'rejected')
                                ❌ Tolak
                            @else
                                ⚠️ Catat Gagal Masak
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
