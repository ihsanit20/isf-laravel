<?php

use App\Enums\FundCycleEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_cycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('status', FundCycleEventStatus::values())->default(FundCycleEventStatus::Draft->value);
            $table->text('description')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->dateTime('order_open_at');
            $table->dateTime('order_close_at');
            $table->date('expected_delivery_date')->nullable();
            $table->timestamps();

            $table->index(['fund_cycle_id', 'status']);
            $table->index('order_open_at');
            $table->index('order_close_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_cycle_events');
    }
};
