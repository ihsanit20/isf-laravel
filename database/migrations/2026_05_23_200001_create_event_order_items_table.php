<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_order_id')->constrained('event_orders')->cascadeOnDelete();
            $table->foreignId('event_package_id')->constrained('event_packages')->restrictOnDelete();

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->index('event_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_order_items');
    }
};
