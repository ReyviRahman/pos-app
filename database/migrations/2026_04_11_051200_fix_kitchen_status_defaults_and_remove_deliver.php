<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix default values from 'pending' to 'waiting'
        Schema::table('orders', function (Blueprint $table) {
            $table->string('kitchen_status')->default('waiting')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('kitchen_status')->default('waiting')->change();
        });

        // Convert any 'deliver' statuses to 'ready' (removing deliver status)
        DB::table('order_items')->where('kitchen_status', 'deliver')->update(['kitchen_status' => 'ready']);
        DB::table('orders')->where('kitchen_status', 'deliver')->update(['kitchen_status' => 'ready']);

        // Convert any remaining 'pending' to 'waiting'
        DB::table('order_items')->where('kitchen_status', 'pending')->update(['kitchen_status' => 'waiting']);
        DB::table('orders')->where('kitchen_status', 'pending')->update(['kitchen_status' => 'waiting']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('kitchen_status')->default('pending')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('kitchen_status')->default('pending')->change();
        });
    }
};
