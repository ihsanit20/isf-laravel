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
            $table->foreignId('charge_category_id')->constrained('charge_categories')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->enum('status', ['pending', 'posted', 'waived', 'cancelled'])->default('pending');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['charge_category_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
