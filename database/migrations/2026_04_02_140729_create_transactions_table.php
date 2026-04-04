<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // Nomor struk unik
            $table->decimal('total_amount', 15, 2)->default(0); // Total belanja
            $table->decimal('paid_amount', 15, 2)->default(0); // Uang yang dibayarkan pelanggan
            $table->decimal('change_amount', 15, 2)->default(0); // Uang kembalian
            $table->string('payment_method')->default('cash'); // cash, qris, transfer, dll
            $table->string('status')->default('completed'); // pending, completed, canceled

            // Opsional: Jika Anda punya sistem multi-kasir/user
            // $table->foreignId('user_id')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
