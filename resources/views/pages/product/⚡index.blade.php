<?php

use App\Models\Product;
use Livewire\Component;

new class extends Component
{
    // Mengirim data ke view
    public function with(): array
    {
        // Eager load 'ingredients' untuk menghindari masalah N+1 query
        return [
            'products' => auth()->user()->branch->products()->with('ingredients')->latest()->get(),
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftar Product') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Notifikasi Sukses --}}
            @if (session()->has('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="p-6 text-gray-900 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-700">Daftar Menu & Kalkulasi Margin</h3>
                    <a href="{{ route('product.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md px-4 py-2 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambahkan Product
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-center w-16">No</th>
                                <th class="py-3 px-6">Nama Produk</th>
                                <th class="py-3 px-6 text-right">Harga Jual</th>
                                <th class="py-3 px-6 text-right">Total HPP (Modal)</th>
                                <th class="py-3 px-6 text-right">Keuntungan</th>
                                <th class="py-3 px-6 text-center">Margin (%)</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            @forelse ($products as $index => $product)
                                @php
                                    $hpp = $product->ingredients->sum(function($ingredient) {
                                        return $ingredient->pivot->quantity_used * ($ingredient->price_per_unit ?? 0);
                                    });
                                    
                                    $profit = $product->price - $hpp;
                                    $marginPercentage = $product->price > 0 ? ($profit / $product->price) * 100 : 0;
                                @endphp

                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="py-3 px-6 text-center font-medium">{{ $index + 1 }}</td>
                                    <td class="py-3 px-6 font-semibold text-gray-800">
                                        {{ $product->name }}
                                        <div class="text-xs text-gray-400 font-normal mt-1">
                                            {{ $product->ingredients->count() }} Komposisi Bahan
                                        </div>
                                    </td>
                                    <td class="py-3 px-6 text-right text-blue-600 font-medium">
                                        Rp {{ number_format($product->price, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-6 text-right text-red-500">
                                        Rp {{ number_format($hpp, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-6 text-right text-green-600 font-bold">
                                        Rp {{ number_format($profit, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold 
                                            {{ $marginPercentage >= 50 ? 'bg-green-100 text-green-700' : ($marginPercentage > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                            {{ number_format($marginPercentage, 1) }}%
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('product.edit', $product->id) }}"
                                               class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Edit
                                            </a>
                                            <button @click="$dispatch('open-product-detail', {{ @json_encode([
                                                'name' => $product->name,
                                                'price' => $product->price,
                                                'hpp' => $hpp,
                                                'profit' => $profit,
                                                'margin' => $marginPercentage,
                                                'ingredients' => $product->ingredients->map(function($ing) {
                                                    return [
                                                        'name' => $ing->name,
                                                        'quantity' => $ing->pivot->quantity_used,
                                                        'unit' => $ing->unit,
                                                        'price_per_unit' => $ing->price_per_unit ?? 0,
                                                        'subtotal' => $ing->pivot->quantity_used * ($ing->price_per_unit ?? 0),
                                                    ];
                                                }),
                                            ]) }})"
                                                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Detail
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-400">
                                        Belum ada data produk yang ditambahkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    {{-- Modal Detail Produk --}}
    <div x-data="{ open: false, product: null }"
         @open-product-detail.window="product = $event.detail; open = true"
         @keydown.escape.window="open = false"
         x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" @click="open = false"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-4"
                 class="bg-white rounded-lg shadow-xl w-full max-w-2xl relative z-10 overflow-hidden"
                 @click.stop>
                <template x-if="product">
                    <div>
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-800" x-text="'Detail: ' + product.name"></h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-6">
                            {{-- Ringkasan Harga & HPP --}}
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-blue-50 rounded-lg p-4 text-center">
                                    <p class="text-xs text-blue-500 font-medium mb-1">Harga Jual</p>
                                    <p class="text-lg font-bold text-blue-700" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.price)"></p>
                                </div>
                                <div class="bg-red-50 rounded-lg p-4 text-center">
                                    <p class="text-xs text-red-500 font-medium mb-1">Total HPP</p>
                                    <p class="text-lg font-bold text-red-700" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.hpp)"></p>
                                </div>
                                <div class="bg-green-50 rounded-lg p-4 text-center">
                                    <p class="text-xs text-green-500 font-medium mb-1">Keuntungan</p>
                                    <p class="text-lg font-bold text-green-700" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.profit)"></p>
                                </div>
                            </div>

                            {{-- Penjelasan Perhitungan --}}
                            <div class="bg-gray-50 rounded-lg p-4 space-y-2 text-sm">
                                <h4 class="font-bold text-gray-700">Penjelasan Perhitungan</h4>
                                <div class="space-y-1 text-gray-600">
                                    <p><span class="font-medium text-gray-700">HPP</span> = Jumlah dari (Qty Bahan × Harga Satuan) setiap bahan baku</p>
                                    <p><span class="font-medium text-gray-700">Keuntungan</span> = Harga Jual − Total HPP</p>
                                    <p><span class="font-medium text-gray-700">Margin</span> = (Keuntungan / Harga Jual) × 100%</p>
                                </div>
                                <div class="border-t border-gray-200 pt-2 mt-2 space-y-1">
                                    <p class="text-gray-700">
                                        <span class="font-medium">HPP</span> = <span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.hpp)"></span>
                                    </p>
                                    <p class="text-gray-700">
                                        <span class="font-medium">Keuntungan</span> = <span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.price)"></span> − <span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.hpp)"></span> = <span class="font-bold text-green-600" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.profit)"></span>
                                    </p>
                                    <p class="text-gray-700">
                                        <span class="font-medium">Margin</span> = (<span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.profit)"></span> / <span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.price)"></span>) × 100% = <span class="font-bold" x-text="product.margin.toFixed(1) + '%'"></span>
                                    </p>
                                </div>
                            </div>

                            {{-- Detail Bahan Baku --}}
                            <div>
                                <h4 class="font-bold text-gray-700 mb-3">Komposisi Bahan Baku</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-xs leading-normal">
                                                <th class="py-2 px-3 text-center w-10">No</th>
                                                <th class="py-2 px-3">Nama Bahan</th>
                                                <th class="py-2 px-3 text-center">Qty Dipakai</th>
                                                <th class="py-2 px-3 text-right">Harga Satuan</th>
                                                <th class="py-2 px-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-600 text-sm">
                                            <template x-for="(ing, index) in product.ingredients" :key="index">
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="py-2 px-3 text-center" x-text="index + 1"></td>
                                                    <td class="py-2 px-3 font-medium text-gray-800" x-text="ing.name"></td>
                                                    <td class="py-2 px-3 text-center">
                                                        <span x-text="ing.quantity"></span>
                                                        <span class="text-xs text-gray-400" x-text="ing.unit"></span>
                                                    </td>
                                                    <td class="py-2 px-3 text-right text-indigo-600" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(ing.price_per_unit)"></td>
                                                    <td class="py-2 px-3 text-right font-semibold text-gray-800" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(ing.subtotal)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-gray-50 font-bold">
                                                <td colspan="4" class="py-2 px-3 text-right text-gray-700">Total HPP</td>
                                                <td class="py-2 px-3 text-right text-red-600" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(product.hpp)"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>