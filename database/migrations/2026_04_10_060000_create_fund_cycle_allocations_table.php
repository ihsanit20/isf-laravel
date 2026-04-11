<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_cycle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('slot_key')->nullable();
            $table->unsignedInteger('amount');
            $table->timestamp('allocated_at');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fund_cycle_id', 'member_id', 'slot_key']);
            $table->index(['fund_cycle_id', 'allocated_at']);
            $table->index(['fund_cycle_id', 'slot_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_cycle_allocations');
    }
};
