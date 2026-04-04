<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('xendit_payment_request_id')->nullable()->after('status');
            $table->string('xendit_payment_url')->nullable()->after('xendit_payment_request_id');
            $table->string('xendit_payment_status')->default('PENDING')->after('xendit_payment_url');
            $table->string('xendit_channel_code')->nullable()->after('xendit_payment_status');
            $table->json('xendit_metadata')->nullable()->after('xendit_channel_code');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'xendit_payment_request_id',
                'xendit_payment_url',
                'xendit_payment_status',
                'xendit_channel_code',
                'xendit_metadata',
            ]);
        });
    }
};
