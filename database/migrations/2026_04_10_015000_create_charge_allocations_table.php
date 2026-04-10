<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained('charges')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['charge_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_allocations');
    }
};
