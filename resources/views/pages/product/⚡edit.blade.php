<?php

use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public Product $product;

    public string $name = '';

    public string $price = '';

    public array $menuIngredients = [];

    public bool $showIngredientModal = false;

    public string $newIngredientName = '';

    public string $newIngredientUnit = '';

    public function mount($id)
    {
        $this->product = Product::with('ingredients')->findOrFail($id);
        $this->name = $this->product->name;
        $this->price = $this->product->price;

        foreach ($this->product->ingredients as $ingredient) {
            $this->menuIngredients[] = [
                'ingredient_id' => $ingredient->id,
                'quantity_used' => $ingredient->pivot->quantity_used,
            ];
        }

        if (empty($this->menuIngredients)) {
            $this->addIngredient();
        }
    }

    public function addIngredient()
    {
        $this->menuIngredients[] = [
            'ingredient_id' => '',
            'quantity_used' => '',
        ];
    }

    public function removeIngredient($index)
    {
        unset($this->menuIngredients[$index]);
        $this->menuIngredients = array_values($this->menuIngredients);
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'menuIngredients' => 'required|array|min:1',
            'menuIngredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'menuIngredients.*.quantity_used' => 'required|numeric|min:0.01',
        ], [
            'menuIngredients.*.ingredient_id.required' => 'Pilih bahan baku terlebih dahulu.',
            'menuIngredients.*.quantity_used.required' => 'Jumlah penggunaan tidak boleh kosong.',
        ]);

        DB::transaction(function () {
            $this->product->update([
                'name' => $this->name,
                'price' => $this->price,
            ]);

            $pivotData = [];
            foreach ($this->menuIngredients as $item) {
                $pivotData[$item['ingredient_id']] = [
                    'quantity_used' => $item['quantity_used'],
                ];
            }

            $this->product->ingredients()->sync($pivotData);
        });

        return redirect()->route('product.index')->with('success', 'Produk dan resep bahan berhasil diperbarui!');
    }

    public function openIngredientModal()
    {
        $this->resetValidation(['newIngredientName', 'newIngredientUnit']);
        $this->reset(['newIngredientName', 'newIngredientUnit']);
        $this->showIngredientModal = true;
    }

    public function closeIngredientModal()
    {
        $this->showIngredientModal = false;
    }

    public function saveNewIngredient()
    {
        $this->validate([
            'newIngredientName' => 'required|string|max:255',
            'newIngredientUnit' => 'required|string|max:50',
        ]);

        Ingredient::create([
            'name' => $this->newIngredientName,
            'unit' => $this->newIngredientUnit,
        ]);

        $this->closeIngredientModal();
        session()->flash('success_ingredient', 'Bahan baku baru berhasil ditambahkan!');
    }

    public function with(): array
    {
        return [
            'availableIngredients' => Ingredient::orderBy('name')->get(),
        ];
    }
};
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md mt-8 relative">
    
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('success_ingredient'))
        <div class="mb-6 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded-md flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            {{ session('success_ingredient') }}
        </div>
    @endif

    <form wire:submit="update">
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Edit Data Produk</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" placeholder="Contoh: Nasi Goreng Spesial" 
                           class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" wire:model="price" placeholder="Contoh: 25000" 
                           class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                    @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="mb-8">
            <div class="flex justify-between items-end border-b pb-2 mb-4">
                <h3 class="text-xl font-bold text-gray-800">Komposisi Bahan (Resep)</h3>
                
                <button type="button" wire:click="openIngredientModal" 
                        class="text-sm bg-indigo-50 text-indigo-600 hover:bg-indigo-100 border border-indigo-200 px-3 py-1.5 rounded-md font-medium transition flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Bahan Baku
                </button>
            </div>
            
            <div class="space-y-4">
                @foreach($menuIngredients as $index => $item)
                    <div class="flex flex-col md:flex-row gap-4 items-start md:items-center bg-gray-50 p-4 rounded-md border border-gray-200">
                        
                        <div class="w-full md:w-1/2">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Pilih Bahan</label>
                            <select wire:model="menuIngredients.{{ $index }}.ingredient_id" 
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 bg-white focus:ring-blue-500 focus:border-blue-500 outline-none">
                                <option value="">-- Pilih Bahan Baku --</option>
                                @foreach($availableIngredients as $ingredient)
                                    <option value="{{ $ingredient->id }}">
                                        {{ $ingredient->name }} ({{ $ingredient->unit }})
                                    </option>
                                @endforeach
                            </select>
                            @error('menuIngredients.'.$index.'.ingredient_id') 
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                            @enderror
                        </div>

                        <div class="w-full md:w-1/3">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Jumlah Dipakai</label>
                            <input type="number" step="0.01" wire:model="menuIngredients.{{ $index }}.quantity_used" placeholder="Contoh: 0.5" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            @error('menuIngredients.'.$index.'.quantity_used') 
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                            @enderror
                        </div>

                        <div class="w-full md:w-auto mt-4 md:mt-5">
                            @if(count($menuIngredients) > 1)
                                <button type="button" wire:click="removeIngredient({{ $index }})" 
                                        class="w-full md:w-auto bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-md transition font-medium">
                                    Hapus
                                </button>
                            @else
                                <div class="w-full md:w-[70px]"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                <button type="button" wire:click="addIngredient" 
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 px-4 py-2 rounded-md font-medium transition flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Baris Bahan
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="{{ route('product.index') }}" 
               class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-2.5 rounded-md font-medium transition">
                Batal
            </a>
            <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-md font-bold shadow-sm transition">
                Simpan Perubahan
            </button>
        </div>
    </form>

    @if($showIngredientModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Tambah Bahan Baku Baru</h3>
                    <button wire:click="closeIngredientModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bahan <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newIngredientName" placeholder="Contoh: Beras Putih" 
                               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        @error('newIngredientName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan (Unit) Untuk 1 kali Masak<span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newIngredientUnit" placeholder="Contoh: kg, gram, liter, pcs" 
                               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        @error('newIngredientUnit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeIngredientModal" 
                            class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">
                        Batal
                    </button>
                    <button type="button" wire:click="saveNewIngredient" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">
                        Simpan Bahan
                    </button>
                </div>
                
            </div>
        </div>
    @endif

</div>
