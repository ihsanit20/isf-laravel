<?php

use App\Enums\EventPackageUnitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_order_items', function (Blueprint $table) {
            $table->enum('unit_type', EventPackageUnitType::values())
                ->nullable()
                ->after('quantity');
            $table->decimal('unit_size', 12, 3)
                ->nullable()
                ->after('unit_type');
        });
    }

    public function down(): void
    {
        Schema::table('event_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'unit_size']);
        });
    }
};
