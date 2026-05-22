<?php

use App\Enums\EventPackageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('advance_percent', 5, 2)->default(0);
            $table->unsignedInteger('min_qty_per_order')->default(1);
            $table->unsignedInteger('max_qty_per_order')->nullable();
            $table->unsignedInteger('stock_qty')->nullable();
            $table->unsignedInteger('sold_qty')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', EventPackageStatus::values())->default(EventPackageStatus::Draft->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_cycle_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_packages');
    }
};
