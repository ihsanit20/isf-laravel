<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_cycles', function (Blueprint $table) {
            $table->json('slots')->nullable()->after('settlement_date');
        });

        Schema::table('fund_cycle_allocations', function (Blueprint $table) {
            $table->string('slot_key')->nullable()->after('member_id');
            $table->dropUnique('fund_cycle_allocations_fund_cycle_id_member_id_unique');
            $table->unique(['fund_cycle_id', 'member_id', 'slot_key']);
            $table->index(['fund_cycle_id', 'slot_key']);
        });
    }

    public function down(): void
    {
        Schema::table('fund_cycle_allocations', function (Blueprint $table) {
            $table->dropUnique('fund_cycle_allocations_fund_cycle_id_member_id_slot_key_unique');
            $table->dropIndex('fund_cycle_allocations_fund_cycle_id_slot_key_index');
            $table->dropColumn('slot_key');
            $table->unique(['fund_cycle_id', 'member_id']);
        });

        Schema::table('fund_cycles', function (Blueprint $table) {
            $table->dropColumn('slots');
        });
    }
};
