<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained('fund_cycle_events')->restrictOnDelete();
            $table->foreignId('event_pickup_point_id')->nullable()->constrained('event_pickup_points')->nullOnDelete();

            $table->string('order_number', 20)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->text('customer_address')->nullable();

            // status: pending | confirmed | cancelled | delivered
            $table->string('status', 20)->default('pending');

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('advance_amount', 12, 2)->default(0);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_cycle_event_id', 'status']);
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_orders');
    }
};
