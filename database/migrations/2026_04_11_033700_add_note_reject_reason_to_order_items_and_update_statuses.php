<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('note')->nullable()->after('price');
            $table->text('reject_reason')->nullable()->after('kitchen_status');
        });

        // Migrate existing status values
        DB::table('order_items')->where('kitchen_status', 'pending')->update(['kitchen_status' => 'waiting']);
        DB::table('order_items')->where('kitchen_status', 'completed')->update(['kitchen_status' => 'ready']);

        DB::table('orders')->where('kitchen_status', 'pending')->update(['kitchen_status' => 'waiting']);
        DB::table('orders')->where('kitchen_status', 'completed')->update(['kitchen_status' => 'ready']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status values
        DB::table('order_items')->where('kitchen_status', 'waiting')->update(['kitchen_status' => 'pending']);
        DB::table('order_items')->where('kitchen_status', 'ready')->update(['kitchen_status' => 'completed']);

        DB::table('orders')->where('kitchen_status', 'waiting')->update(['kitchen_status' => 'pending']);
        DB::table('orders')->where('kitchen_status', 'ready')->update(['kitchen_status' => 'completed']);

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['note', 'reject_reason']);
        });
    }
};
