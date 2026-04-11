<?php

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Component;

new class extends Component
{
    public function getOrdersProperty()
    {
        return Order::with('items.product')
            ->where('branch_id', auth()->user()->branch_id)
            ->whereIn('kitchen_status', ['pending', 'cooking'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function changeStatus($orderId, $newStatus)
    {
        $order = Order::where('branch_id', auth()->user()->branch_id)->findOrFail($orderId);

        if ($newStatus === 'cooking') {
            $order->items()->where('kitchen_status', 'pending')->update(['kitchen_status' => 'cooking']);
        } elseif ($newStatus === 'completed') {
            $order->items()->whereIn('kitchen_status', ['pending', 'cooking'])->update(['kitchen_status' => 'completed']);
        }

        $order->refresh();
        $hasPendingItems = $order->items()->where('kitchen_status', 'pending')->exists();
        $hasCookingItems = $order->items()->where('kitchen_status', 'cooking')->exists();

        if ($hasPendingItems) {
            $order->update(['kitchen_status' => 'pending']);
        } elseif ($hasCookingItems) {
            $order->update(['kitchen_status' => 'cooking']);
        } else {
            $order->update(['kitchen_status' => $newStatus]);
        }

        $this->dispatch('custom-notify', message: 'Status masakan diperbarui!', type: 'success');
    }

    public function changeItemStatus($itemId, $newStatus)
    {
        $item = OrderItem::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->findOrFail($itemId);

        $item->update(['kitchen_status' => $newStatus]);

        $order = $item->order;
        $hasPendingItems = $order->items()->where('kitchen_status', 'pending')->exists();
        $hasCookingItems = $order->items()->where('kitchen_status', 'cooking')->exists();

        if ($hasPendingItems) {
            $order->update(['kitchen_status' => 'pending']);
        } elseif ($hasCookingItems) {
            $order->update(['kitchen_status' => 'cooking']);
        } else {
            $order->update(['kitchen_status' => 'completed']);
        }

        $this->dispatch('custom-notify', message: 'Item masakan diperbarui!', type: 'success');
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
            showToast = true; // Gunakan showToast untuk memunculkan
            if(toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => showToast = false, 3500); // Sembunyikan tanpa menghapus tipe
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
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-start 
                            {{ $order->kitchen_status === 'cooking' ? 'bg-amber-50' : 'bg-slate-50' }}">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-xl">{{ $order->table_number ? 'Meja ' . $order->table_number : 'Take Away' }}</h3>
                                <p class="text-sm font-medium text-slate-500">{{ $order->customer_name }} • {{ $order->order_number }}</p>
                                @if($order->note)
                                    <div class="mt-2 bg-yellow-100/80 border border-yellow-300 text-yellow-800 text-xs font-bold px-2.5 py-1.5 rounded-lg shadow-sm">
                                        <span class="block text-[10px] uppercase tracking-wider text-yellow-600 mb-0.5">Catatan Pesanan:</span>
                                        {{ $order->note }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                @if($order->kitchen_status === 'pending')
                                    <span class="px-3 py-1 rounded-full bg-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wide">Belum Dimasak</span>
                                @elseif($order->kitchen_status === 'cooking')
                                    <span class="px-3 py-1 rounded-full bg-amber-200 text-amber-800 text-xs font-bold uppercase tracking-wide flex items-center gap-1">
                                        <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        Sedang Dimasak
                                    </span>
                                @endif
                                <div class="text-xs text-slate-400 mt-2 text-right">
                                    {{ $order->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-5 flex-1 bg-white">
                            <ul class="space-y-3">
                                @foreach($order->items as $item)
                                    @php
                                        $isPending = $item->kitchen_status === 'pending';
                                        $isCooking = $item->kitchen_status === 'cooking';
                                        $isCooked = in_array($item->kitchen_status, ['completed', 'served']);
                                    @endphp
                                    <li class="flex justify-between items-start {{ $isCooked ? 'opacity-40' : '' }}">
                                        <div class="flex gap-3">
                                            <span class="font-black {{ $isCooked ? 'text-slate-500 bg-slate-200' : 'text-indigo-600 bg-indigo-50' }} w-8 h-8 flex items-center justify-center rounded-lg">{{ $item->quantity }}x</span>
                                            <div>
                                                <p class="font-bold {{ $isCooked ? 'text-slate-500 line-through' : 'text-slate-800' }}">
                                                    {{ $item->product_name ?? ($item->product ? $item->product->name : 'Menu Dihapus') }}
                                                </p>
                                                @if($isCooked)
                                                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Sudah Siap</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-1">
                                            @if($isPending)
                                                <button wire:click="changeItemStatus({{ $item->id }}, 'cooking')" class="text-xs bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-2 py-1 rounded font-bold transition" title="Mulai Masak">
                                                    Masak
                                                </button>
                                            @elseif($isCooking)
                                                <button wire:click="changeItemStatus({{ $item->id }}, 'completed')" class="text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-2 py-1 rounded font-bold transition" title="Tandai Selesai">
                                                    Jadi
                                                </button>
                                            @elseif($isCooked)
                                                <button wire:click="changeItemStatus({{ $item->id }}, 'cooking')" class="text-xs bg-amber-100 text-amber-700 hover:bg-amber-200 px-2 py-1 rounded font-bold transition" title="Kembalikan ke Cooking">
                                                    Urungkan
                                                </button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="p-5 border-t border-slate-100 bg-slate-50 mt-auto">
                            @if($order->kitchen_status === 'pending')
                                <button wire:click="changeStatus({{ $order->id }}, 'cooking')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                    Mulai Masak
                                </button>
                            @elseif($order->kitchen_status === 'cooking')
                                <button wire:click="changeStatus({{ $order->id }}, 'completed')" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-4 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Pesanan Siap
                                </button>
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
    </div>
</div>
