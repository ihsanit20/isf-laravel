<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->date('allocation_month');
            $table->unsignedInteger('units');
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('allocated_amount');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['deposit_submission_id', 'member_id']);
            $table->index(['member_id', 'allocation_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_allocations');
    }
};
