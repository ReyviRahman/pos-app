<?php

use Livewire\Component;
use App\Models\InventoryMovement;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    // Properti Form Modal
    public bool $showModal = false;
    public string $ingredient_id = '';
    public string $type = 'in'; // Default 'in' (Masuk)
    public string $quantity = '';
    public string $reference_id = '';
    
    // Properti Harga
    public string $price_per_unit = '';

    public function openModal()
    {
        $this->resetValidation();
        $this->reset(['ingredient_id', 'quantity', 'reference_id', 'price_per_unit']);
        $this->type = 'in';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function save()
    {
        $this->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0.01',
            'reference_id' => 'nullable|string|max:255',
            'price_per_unit' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () {
            // Tangani input kosong menjadi null agar tidak error di database
            $priceInput = $this->price_per_unit !== '' ? $this->price_per_unit : null;

            // 1. Simpan riwayat pergerakan (Movement) TERMASUK HARGA
            InventoryMovement::create([
                'ingredient_id' => $this->ingredient_id,
                'type' => $this->type,
                'quantity' => $this->quantity,
                'price_per_unit' => $priceInput, // Disimpan ke riwayat
                'reference_id' => $this->reference_id,
            ]);

            // 2. Update stok & harga master di tabel Ingredients
            $ingredient = Ingredient::find($this->ingredient_id);
            
            if ($this->type === 'in') {
                $ingredient->current_stock += $this->quantity;
            } else {
                $ingredient->current_stock -= $this->quantity;
            }

            // Update harga master jika diinputkan
            if ($priceInput !== null) {
                $ingredient->price_per_unit = $priceInput;
            }

            $ingredient->save();
        });

        $this->closeModal();
        session()->flash('success', 'Transaksi berhasil dicatat dan data bahan diperbarui!');
    }

    public function with(): array
    {
        return [
            'movements' => InventoryMovement::with('ingredient')->latest()->get(),
            'ingredients' => Ingredient::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bahan Masuk/Keluar') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session()->has('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-700">Riwayat Pergerakan Stok</h3>
                    <button wire:click="openModal" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md px-4 py-2 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Catat Transaksi Baru
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-center w-16">Tanggal</th>
                                <th class="py-3 px-6">Bahan Baku</th>
                                <th class="py-3 px-6 text-center">Tipe</th>
                                <th class="py-3 px-6 text-right">Jumlah</th>
                                <th class="py-3 px-6 text-right">Harga saat itu</th>
                                <th class="py-3 px-6">Referensi / Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            @forelse ($movements as $movement)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="py-3 px-6 text-center whitespace-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-3 px-6 font-semibold text-gray-800">{{ optional($movement->ingredient)->name ?? 'Bahan Dihapus' }}</td>
                                    <td class="py-3 px-6 text-center">
                                        @if($movement->type === 'in')
                                            <span class="bg-green-100 text-green-700 py-1 px-3 rounded-full text-xs font-bold">Masuk</span>
                                        @else
                                            <span class="bg-red-100 text-red-700 py-1 px-3 rounded-full text-xs font-bold">Keluar</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-6 text-right font-medium {{ $movement->type === 'in' ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $movement->type === 'in' ? '+' : '-' }}{{ floatval($movement->quantity) }} 
                                        <span class="text-xs text-gray-400">{{ optional($movement->ingredient)->unit }}</span>
                                    </td>
                                    <td class="py-3 px-6 text-right">
                                        @if($movement->price_per_unit)
                                            <span class="text-indigo-600 font-medium">Rp {{ number_format($movement->price_per_unit, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-6 text-gray-500 italic text-sm">{{ $movement->reference_id ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">Belum ada riwayat pergerakan stok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Catat Bahan Masuk/Keluar</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bahan Baku <span class="text-red-500">*</span></label>
                        <select wire:model="ingredient_id" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <option value="">-- Pilih Bahan Baku --</option>
                            @foreach($ingredients as $ingredient)
                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }} (Stok: {{ floatval($ingredient->current_stock) }} {{ $ingredient->unit }})</option>
                            @endforeach
                        </select>
                        @error('ingredient_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe <span class="text-red-500">*</span></label>
                            <select wire:model="type" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                <option value="in">Masuk (Penambahan)</option>
                                <option value="out">Keluar (Pengurangan)</option>
                            </select>
                            @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" wire:model="quantity" placeholder="Contoh: 10" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('quantity') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t pt-4 mt-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal / Satuan</label>
                            <input type="number" step="0.01" wire:model="price_per_unit" placeholder="Contoh: 15000" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <span class="text-[10px] text-gray-400 mt-1 block leading-tight">Opsional. Mengupdate harga master & tercatat di riwayat.</span>
                            @error('price_per_unit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan / No. Faktur</label>
                            <input type="text" wire:model="reference_id" placeholder="Opsional" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('reference_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeModal" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Simpan Transaksi</button>
                </div>
            </div>
        </div>
    @endif
</div>