<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE event_packages CHANGE unit_price package_price DECIMAL(12, 2) NOT NULL');
        DB::statement('ALTER TABLE event_order_items CHANGE unit_price package_price DECIMAL(12, 2) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_order_items CHANGE package_price unit_price DECIMAL(12, 2) NOT NULL');
        DB::statement('ALTER TABLE event_packages CHANGE package_price unit_price DECIMAL(12, 2) NOT NULL');
    }
};
