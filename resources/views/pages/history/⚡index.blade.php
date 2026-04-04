<?php

use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $start_date = '';

    public string $end_date = '';

    public ?int $selectedTransactionId = null;

    public function openDetail($transactionId)
    {
        $this->selectedTransactionId = $transactionId;
        $this->dispatch('open-modal', name: 'transaction-detail');
    }

    public function closeModal()
    {
        $this->selectedTransactionId = null;
        $this->dispatch('close-modal', name: 'transaction-detail');
    }

    public function resetFilters()
    {
        $this->reset(['search', 'start_date', 'end_date']);
        $this->resetPage();
    }

    public function getTransactionsProperty()
    {
        $query = Transaction::query()->with('details')->latest();

        if ($this->search !== '') {
            $query->where('invoice_number', 'like', '%'.$this->search.'%');
        }

        if ($this->start_date !== '') {
            $query->whereDate('created_at', '>=', $this->start_date);
        }

        if ($this->end_date !== '') {
            $query->whereDate('created_at', '<=', $this->end_date);
        }

        return $query->paginate(10);
    }

    public function getSelectedTransactionProperty()
    {
        if (! $this->selectedTransactionId) {
            return null;
        }

        return Transaction::with('details')->find($this->selectedTransactionId);
    }

    public function with(): array
    {
        return [
            'transactions' => $this->transactions,
            'selectedTransaction' => $this->selectedTransaction,
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Riwayat Pembayaran') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session()->has('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filter & Search --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cari Invoice</label>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ketik nomor invoice..."
                                   class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="w-full md:w-48">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal Mulai</label>
                            <input type="date" wire:model.live="start_date"
                                   class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="w-full md:w-48">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal Akhir</label>
                            <input type="date" wire:model.live="end_date"
                                   class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button wire:click="reset_filters"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-md transition">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Tabel Transaksi --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-700">Daftar Transaksi</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-center w-16">No</th>
                                <th class="py-3 px-6">Invoice</th>
                                <th class="py-3 px-6">Tanggal</th>
                                <th class="py-3 px-6 text-right">Total</th>
                                <th class="py-3 px-6">Metode Bayar</th>
                                <th class="py-3 px-6 text-center">Status</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm">
                            @forelse ($transactions as $index => $transaction)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="py-3 px-6 text-center font-medium">
                                        {{ $transactions->firstItem() + $index }}
                                    </td>
                                    <td class="py-3 px-6 font-semibold text-gray-800">
                                        {{ $transaction->invoice_number }}
                                    </td>
                                    <td class="py-3 px-6">
                                        {{ $transaction->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3 px-6 text-right font-bold text-gray-800">
                                        Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-6">
                                        @php
                                            $methodLabels = [
                                                'cash' => 'Tunai',
                                                'qris' => 'QRIS',
                                                'qris_edc' => 'QRIS EDC',
                                                'gopay' => 'Gopay Speaker',
                                                'midtrans_qris' => 'BRI',
                                                'transfer' => 'Transfer',
                                            ];
                                            $methodColors = [
                                                'cash' => 'bg-green-100 text-green-700',
                                                'qris' => 'bg-blue-100 text-blue-700',
                                                'qris_edc' => 'bg-indigo-100 text-indigo-700',
                                                'gopay' => 'bg-orange-100 text-orange-700',
                                                'midtrans_qris' => 'bg-teal-100 text-teal-700',
                                                'transfer' => 'bg-purple-100 text-purple-700',
                                            ];
                                            $method = $transaction->payment_method;
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-bold {{ $methodColors[$method] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $methodLabels[$method] ?? ucfirst($method) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        @php
                                            $statusLabels = [
                                                'completed' => 'Selesai',
                                                'pending' => 'Pending',
                                                'canceled' => 'Dibatalkan',
                                            ];
                                            $statusColors = [
                                                'completed' => 'bg-green-100 text-green-700',
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'canceled' => 'bg-red-100 text-red-700',
                                            ];
                                            $status = $transaction->status;
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-bold {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusLabels[$status] ?? ucfirst($status) }}
                                        </span>
                                        @if($transaction->xendit_payment_status && in_array($transaction->payment_method, ['qris', 'transfer', 'midtrans_qris']))
                                            <div class="mt-1">
                                                @php
                                                    $xenditStatusColors = [
                                                        'SUCCEEDED' => 'bg-green-100 text-green-700',
                                                        'PENDING' => 'bg-blue-100 text-blue-700',
                                                        'FAILED' => 'bg-red-100 text-red-700',
                                                        'EXPIRED' => 'bg-gray-100 text-gray-700',
                                                        'REQUIRES_ACTION' => 'bg-yellow-100 text-yellow-700',
                                                    ];
                                                @endphp
                                                <span class="px-2 py-0.5 rounded-full text-xs {{ $xenditStatusColors[$transaction->xendit_payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                                    {{ $transaction->getPaymentStatusLabel() }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <button wire:click="openDetail({{ $transaction->id }})"
                                                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-400">
                                        Tidak ada data transaksi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t border-gray-200">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detail Transaksi --}}
    <x-modal name="transaction-detail" :show="$selectedTransactionId !== null" maxWidth="lg">
        @if ($selectedTransaction)
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Detail Transaksi</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Info Transaksi --}}
                <div class="bg-gray-50 rounded-lg p-4 mb-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Invoice</span>
                        <span class="font-semibold text-gray-800">{{ $selectedTransaction->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Tanggal</span>
                        <span class="font-semibold text-gray-800">{{ $selectedTransaction->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Metode Bayar</span>
                        <span class="font-semibold text-gray-800">{{ ucfirst($selectedTransaction->payment_method) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Dibayar</span>
                        <span class="font-semibold text-gray-800">Rp {{ number_format($selectedTransaction->paid_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Kembalian</span>
                        <span class="font-semibold text-green-600">Rp {{ number_format($selectedTransaction->change_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between text-sm">
                        <span class="text-gray-700 font-semibold">Total</span>
                        <span class="text-lg font-bold text-gray-800">Rp {{ number_format($selectedTransaction->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- Detail Item --}}
                <h4 class="text-sm font-bold text-gray-700 mb-2">Item yang Dibeli</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-xs leading-normal">
                                <th class="py-2 px-3 text-center w-12">No</th>
                                <th class="py-2 px-3">Produk</th>
                                <th class="py-2 px-3 text-center">Qty</th>
                                <th class="py-2 px-3 text-right">Harga</th>
                                <th class="py-2 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm">
                            @foreach ($selectedTransaction->details as $index => $detail)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 px-3 text-center">{{ $index + 1 }}</td>
                                    <td class="py-2 px-3 font-medium text-gray-800">{{ $detail->product_name }}</td>
                                    <td class="py-2 px-3 text-center">{{ $detail->quantity }}</td>
                                    <td class="py-2 px-3 text-right">Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right font-semibold">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-modal>
</div>
