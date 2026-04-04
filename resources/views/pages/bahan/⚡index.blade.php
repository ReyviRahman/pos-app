<?php

use App\Models\Ingredient;
use Livewire\Component;

new class extends Component
{
    public bool $showEditModal = false;

    public ?int $editIngredientId = null;

    public string $editName = '';

    public string $editCurrentStock = '';

    public string $editUnit = '';

    public string $editPricePerUnit = '';

    public bool $showDeleteConfirm = false;

    public ?int $deleteIngredientId = null;

    public string $deleteIngredientName = '';

    public function openEditModal($ingredientId)
    {
        $ingredient = Ingredient::findOrFail($ingredientId);
        $this->editIngredientId = $ingredient->id;
        $this->editName = $ingredient->name;
        $this->editCurrentStock = $ingredient->current_stock;
        $this->editUnit = $ingredient->unit;
        $this->editPricePerUnit = $ingredient->price_per_unit ?? '';
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editIngredientId = null;
    }

    public function updateIngredient()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editCurrentStock' => 'required|numeric|min:0',
            'editUnit' => 'required|string|max:50',
            'editPricePerUnit' => 'nullable|numeric|min:0',
        ]);

        $ingredient = Ingredient::findOrFail($this->editIngredientId);
        $ingredient->update([
            'name' => $this->editName,
            'current_stock' => $this->editCurrentStock,
            'unit' => $this->editUnit,
            'price_per_unit' => $this->editPricePerUnit !== '' ? $this->editPricePerUnit : null,
        ]);

        $this->closeEditModal();
        session()->flash('success', 'Bahan baku berhasil diperbarui!');
    }

    public function confirmDelete($ingredientId)
    {
        $ingredient = Ingredient::findOrFail($ingredientId);
        $this->deleteIngredientId = $ingredient->id;
        $this->deleteIngredientName = $ingredient->name;
        $this->showDeleteConfirm = true;
    }

    public function closeDeleteConfirm()
    {
        $this->showDeleteConfirm = false;
        $this->deleteIngredientId = null;
    }

    public function deleteIngredient()
    {
        $ingredient = Ingredient::findOrFail($this->deleteIngredientId);
        $ingredient->delete();

        $this->closeDeleteConfirm();
        session()->flash('success', 'Bahan baku berhasil dihapus!');
    }

    public function with(): array
    {
        return [
            'ingredients' => Ingredient::latest()->get(),
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftar Bahan') }}
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
                <div class="p-6 text-gray-900 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-700">Manajemen Stok Bahan Baku</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-center w-16">No</th>
                                <th class="py-3 px-6">Nama Bahan</th>
                                <th class="py-3 px-6 text-right">Stok Saat Ini</th>
                                <th class="py-3 px-6 text-center">Satuan</th>
                                <th class="py-3 px-6 text-right">Harga / Satuan</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            @forelse ($ingredients as $index => $ingredient)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="py-3 px-6 text-center font-medium">{{ $index + 1 }}</td>
                                    
                                    <td class="py-3 px-6 font-semibold text-gray-800">
                                        {{ $ingredient->name }}
                                    </td>
                                    
                                    <td class="py-3 px-6 text-right">
                                        <span class="font-medium {{ $ingredient->current_stock <= 0 ? 'text-red-500' : 'text-gray-700' }}">
                                            {{ floatval($ingredient->current_stock) }}
                                        </span>
                                    </td>
                                    
                                    <td class="py-3 px-6 text-center">
                                        <span class="bg-gray-200 text-gray-700 py-1 px-3 rounded-full text-xs font-medium">
                                            {{ $ingredient->unit }}
                                        </span>
                                    </td>
                                    
                                    <td class="py-3 px-6 text-right text-indigo-600 font-medium">
                                        Rp {{ number_format($ingredient->price_per_unit, 0, ',', '.') }}
                                    </td>
                                    
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button wire:click="openEditModal({{ $ingredient->id }})"
                                                    class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Edit
                                            </button>
                                            <button wire:click="confirmDelete({{ $ingredient->id }})"
                                                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">
                                        Belum ada data bahan baku yang ditambahkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    {{-- Modal Edit Bahan --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Edit Bahan Baku</h3>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bahan <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="editName" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        @error('editName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Saat Ini <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" wire:model="editCurrentStock" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            @error('editCurrentStock') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="editUnit" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            @error('editUnit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga / Satuan</label>
                        <input type="number" step="0.01" wire:model="editPricePerUnit" placeholder="Opsional" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        @error('editPricePerUnit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeEditModal" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="updateIngredient" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Konfirmasi Hapus</h3>
                </div>

                <div class="px-6 py-6">
                    <p class="text-gray-600 text-sm">
                        Apakah Anda yakin ingin menghapus bahan baku <strong class="text-gray-800">{{ $deleteIngredientName }}</strong>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        Peringatan: Bahan ini mungkin masih digunakan dalam resep produk. Menghapusnya dapat menyebabkan error pada data terkait.
                    </p>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeDeleteConfirm" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="deleteIngredient" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>