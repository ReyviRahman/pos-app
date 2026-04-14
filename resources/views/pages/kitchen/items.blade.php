<?php

use App\Models\KitchenWaste;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Component;

new class extends Component
{
    public $sortBy = 'time';

    public $filterTable = '';

    public $filterMenu = '';

    public $rejectItemId = null;

    public $rejectReason = '';

    public $rejectType = '';

    public function getAllItemsProperty()
    {
        $query = OrderItem::whereHas('order', function ($q) {
            $q->where('branch_id', auth()->user()->branch_id);
        })->where('kitchen_status', 'waiting');

        if ($this->filterTable) {
            $query->whereHas('order', function ($q) {
                $q->where('table_number', 'like', '%'.$this->filterTable.'%');
            });
        }

        if ($this->filterMenu) {
            $query->where(function ($q) {
                $q->where('product_name', 'like', '%'.$this->filterMenu.'%')
                    ->orWhereHas('product', function ($q2) {
                        $q2->where('name', 'like', '%'.$this->filterMenu.'%');
                    });
            });
        }

        $items = $query->with(['order', 'product'])->get();

        return match ($this->sortBy) {
            'time' => $items->sortBy('created_at'),
            'menu' => $items->sortBy(function ($item) {
                return $item->product_name ?? ($item->product?->name ?? '');
            }),
            'table' => $items->sortBy(function ($item) {
                return $item->order->table_number ?? 'ZZZ';
            }),
            default => $items,
        };
    }

    public function getTablesProperty()
    {
        return Order::where('branch_id', auth()->user()->branch_id)
            ->whereHas('items', function ($query) {
                $query->where('kitchen_status', 'waiting');
            })
            ->pluck('table_number')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function getMenuOptionsProperty()
    {
        return OrderItem::whereHas('order', function ($q) {
            $q->where('branch_id', auth()->user()->branch_id);
        })->where('kitchen_status', 'waiting')
            ->with('product')
            ->get()
            ->map(function ($item) {
                return $item->product_name ?? ($item->product?->name ?? '');
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function changeItemStatus($itemId, $newStatus)
    {
        $item = OrderItem::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->findOrFail($itemId);

        $item->update(['kitchen_status' => $newStatus]);
        $this->syncOrderKitchenStatus($item->order);

        $this->dispatch('custom-notify', message: 'Status diperbarui!', type: 'success');
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
            KitchenWaste::create([
                'branch_id' => auth()->user()->branch_id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'reason' => $this->rejectReason,
                'chef_name' => auth()->user()->name,
            ]);
            $this->dispatch('custom-notify', message: 'Gagal masak dicatat!', type: 'success');
        } else {
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
        } elseif ($statuses->contains('rejected') && ! $statuses->contains('served')) {
            $order->update(['kitchen_status' => 'rejected']);
        } else {
            $order->update(['kitchen_status' => 'served']);
        }
    }
};
?>

<div class="min-h-screen bg-slate-50 font-sans"
    x-data="{ showToast: false, toastMessage: '', toastType: 'success', toastTimeout: null }"
    @custom-notify.window="toastMessage = $event.detail.message; toastType = $event.detail.type; showToast = true; if(toastTimeout) clearTimeout(toastTimeout); toastTimeout = setTimeout(() => showToast = false, 3500);">

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
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Urutkan berdasarkan</label>
                    <div class="flex gap-2">
                        <button wire:click="$set('sortBy', 'time')" class="flex-1 py-2 px-4 rounded-lg font-medium text-sm transition {{ $sortBy === 'time' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            🕐 Waktu
                        </button>
                        <button wire:click="$set('sortBy', 'menu')" class="flex-1 py-2 px-4 rounded-lg font-medium text-sm transition {{ $sortBy === 'menu' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            🍽️ Menu
                        </button>
                        <button wire:click="$set('sortBy', 'table')" class="flex-1 py-2 px-4 rounded-lg font-medium text-sm transition {{ $sortBy === 'table' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            🪑 Meja
                        </button>
                    </div>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Filter Meja</label>
                    <select wire:model="filterTable" class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                        <option value="">Semua Meja</option>
                        @foreach($this->tables as $table)
                            <option value="{{ $table }}">Meja {{ $table }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Filter Menu</label>
                    <input wire:model="filterMenu" type="text" placeholder="Cari menu..." class="w-full p-2.5 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                </div>
            </div>
        </div>

        <div wire:poll.5s>
            @if(count($this->allItems) > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    @foreach($this->allItems as $item)
                        @php
                            $isWaiting = $item->kitchen_status === 'waiting';
                            $isReady = $item->kitchen_status === 'ready';
                            $order = $item->order;
                        @endphp
                        <div wire:key="item-{{ $item->id }}" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                            <div class="p-3 border-b border-slate-100 bg-slate-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-800">{{ $order->table_number ? 'Meja ' . $order->table_number : 'Take Away' }}</p>
                                        <p class="text-xs text-slate-500">{{ $order->customer_name }}</p>
                                    </div>
                                    <span class="text-xs text-slate-400">{{ $item->created_at->diffForHumans() }}</span>
                                </div>
                            </div>

                            <div class="p-3 flex-1">
                                <div class="flex items-start gap-2">
                                    <span class="font-black w-7 h-7 flex items-center justify-center rounded text-sm
                                        {{ $isWaiting ? 'text-indigo-600 bg-indigo-50' : 'text-emerald-600 bg-emerald-100' }}">
                                        {{ $item->quantity }}x
                                    </span>
                                    <div class="flex-1">
                                        <p class="font-bold text-slate-800 text-sm">
                                            {{ $item->product_name ?? ($item->product ? $item->product->name : 'Menu Dihapus') }}
                                        </p>
                                        @if($item->note)
                                            <p class="text-xs text-slate-500 italic">📝 {{ $item->note }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="p-2 border-t border-slate-100 bg-slate-50">
                                @if($isWaiting)
                                    <div class="flex gap-1">
                                        <button wire:click="changeItemStatus({{ $item->id }}, 'ready')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg font-bold text-xs hover:bg-emerald-600 transition">
                                            ✅
                                        </button>
                                        <button wire:click="openRejectModal({{ $item->id }}, 'rejected')" class="bg-rose-50 text-rose-600 hover:bg-rose-100 py-1.5 px-2 rounded-lg font-bold text-xs transition border border-rose-200">
                                            ❌
                                        </button>
                                        <button wire:click="openRejectModal({{ $item->id }}, 'waste')" class="bg-orange-50 text-orange-600 hover:bg-orange-100 py-1.5 px-2 rounded-lg font-bold text-xs transition border border-orange-200">
                                            ⚠️
                                        </button>
                                    </div>
                                @else
                                    <div class="text-center py-1">
                                        <span class="text-emerald-600 font-bold text-xs">✅</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-slate-400 bg-white rounded-2xl border border-slate-200">
                    <svg class="w-20 h-20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="text-xl font-medium">Tidak ada pesanan</p>
                    <p class="text-sm mt-1">Pesanan akan muncul di sini</p>
                </div>
            @endif
        </div>
    </div>

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
                            ⚠️ Catat
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
