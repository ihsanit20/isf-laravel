<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payments', function (Blueprint $table): void {
            $table->string('payment_type', 20)->default('advance')->after('payment_method');
        });

        DB::table('event_payments')
            ->where('payment_method', 'bkash')
            ->update(['payment_type' => 'advance']);
    }

    public function down(): void
    {
        Schema::table('event_payments', function (Blueprint $table): void {
            $table->dropColumn('payment_type');
        });
    }
};
