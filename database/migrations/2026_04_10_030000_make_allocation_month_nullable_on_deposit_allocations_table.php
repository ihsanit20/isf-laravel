<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('deposit_allocations', function (Blueprint $table) {
                $table->date('allocation_month')->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE deposit_allocations MODIFY allocation_month DATE NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('deposit_allocations', function (Blueprint $table) {
                $table->date('allocation_month')->nullable(false)->change();
            });

            return;
        }

        DB::statement('UPDATE deposit_allocations SET allocation_month = DATE(confirmed_at) WHERE allocation_month IS NULL');
        DB::statement('ALTER TABLE deposit_allocations MODIFY allocation_month DATE NOT NULL');
    }
};
