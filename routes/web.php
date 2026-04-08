<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::livewire('/order', 'pages::pos.order-page')->middleware('role:waiter')->name('order');
    Route::livewire('/payment', 'pages::pos.payment-page')->middleware('role:kasir')->name('payment');
    Route::livewire('/history', 'pages::history.index')->middleware('role:kasir,admin,manajer')->name('history.index');
    Route::livewire('/products', 'pages::product.index')->middleware('role:admin,manajer')->name('product.index');
    Route::livewire('/products/create', 'pages::product.create')->middleware('role:admin,manajer')->name('product.create');
    Route::livewire('/products/{id}/edit', 'pages::product.edit')->middleware('role:admin,manajer')->name('product.edit');
    Route::livewire('/bahans', 'pages::bahan.index')->middleware('role:admin,manajer')->name('bahan.index');
    Route::livewire('/inventory-movement', 'pages::inventory-movement.index')->middleware('role:admin,manajer')->name('inventory-movement.index');

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
