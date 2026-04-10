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
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('username_cashier'); // Nama cashier yang melakukan transaksi
            $table->string('customer_name'); // Nama pelanggan
            $table->string('table_number'); // Nomor meja
            $table->string('invoice_number')->unique(); // Nomor struk unik
            $table->decimal('total_amount', 15, 2)->default(0); // Total belanja
            $table->decimal('paid_amount', 15, 2)->default(0); // Uang yang dibayarkan pelanggan
            $table->decimal('change_amount', 15, 2)->default(0); // Uang kembalian
            $table->string('payment_method')->default('cash'); // cash, qris, transfer, dll
            $table->string('status')->default('completed'); // pending, completed, canceled
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->integer('dibayar_perusahaan')->default(0);
            $table->integer('dibayar_karyawan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
