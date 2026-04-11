<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('order_number')->unique();
            $table->string('username_cashier')->nullable();
            $table->string('customer_name');
            $table->string('table_number', 10);
            $table->decimal('total_price', 15, 2);
            $table->string('status')->default('unpaid');
            $table->string('kitchen_status')->default('waiting');
            $table->index(['branch_id', 'kitchen_status']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
