<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->morphs('chargeable');
            $table->string('title');
            $table->unsignedInteger('amount');
            $table->enum('status', ['pending', 'posted', 'waived', 'cancelled'])->default('pending');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};