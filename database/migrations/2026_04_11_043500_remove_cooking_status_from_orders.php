<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert any remaining 'cooking' statuses to 'waiting'
        DB::table('order_items')->where('kitchen_status', 'cooking')->update(['kitchen_status' => 'waiting']);
        DB::table('orders')->where('kitchen_status', 'cooking')->update(['kitchen_status' => 'waiting']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
