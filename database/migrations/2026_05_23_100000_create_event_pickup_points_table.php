<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_pickup_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('area', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_cycle_event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_pickup_points');
    }
};
