<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payments', function (Blueprint $table) {
            $table->string('bkash_payment_id', 100)->nullable()->after('payment_method');
            $table->string('merchant_invoice', 100)->nullable()->after('bkash_payment_id');

            $table->index('bkash_payment_id');
            $table->index('merchant_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('event_payments', function (Blueprint $table) {
            $table->dropIndex(['bkash_payment_id']);
            $table->dropIndex(['merchant_invoice']);
            $table->dropColumn(['bkash_payment_id', 'merchant_invoice']);
        });
    }
};
