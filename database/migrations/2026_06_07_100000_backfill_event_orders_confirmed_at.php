<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_orders')
            ->whereNull('confirmed_at')
            ->whereIn('status', ['confirmed', 'delivered'])
            ->update([
                'confirmed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        // Non-destructive backfill; no rollback.
    }
};
