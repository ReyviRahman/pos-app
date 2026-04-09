<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            // nullable/nullOnDelete agar jika produk dihapus dari master, riwayat transaksi tidak error
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name'); // Simpan nama produk sebagai histori jaga-jaga jika produk dihapus
            $table->integer('quantity'); // Jumlah produk yang dibeli
            $table->decimal('price', 15, 2); // Harga produk SAAT TRANSAKSI TERJADI
            $table->decimal('subtotal', 15, 2); // quantity * price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
