<?php

use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\XenditWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/xendit', [XenditWebhookController::class, 'handle'])->name('webhook.xendit');
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhook.midtrans');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth', 'verified')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::livewire('/kasir', 'pages::kasir.index')->name('kasir.index');
    Route::livewire('/history', 'pages::history.index')->name('history.index');
    Route::livewire('/products', 'pages::product.index')->name('product.index');
    Route::livewire('/products/create', 'pages::product.create')->name('product.create');
    Route::livewire('/products/{id}/edit', 'pages::product.edit')->name('product.edit');
    Route::livewire('/bahans', 'pages::bahan.index')->name('bahan.index');
    Route::livewire('/inventory-movement', 'pages::inventory-movement.index')->name('inventory-movement.index');

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
