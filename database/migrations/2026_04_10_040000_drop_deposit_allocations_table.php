<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('deposit_allocations');
    }

    public function down(): void
    {
        Schema::create('deposit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->date('allocation_month')->nullable();
            $table->unsignedInteger('units');
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('allocated_amount');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'allocation_month']);
        });
    }
};
