<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('phone', 20)->nullable();
            $table->string('relationship_to_user', 20);
            $table->unsignedInteger('units')->default(1);
            $table->string('status', 20)->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['managed_by_user_id', 'status']);
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
