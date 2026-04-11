<?php

use App\Enums\MemberStatus;
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
            $table->enum('status', MemberStatus::values())->default(MemberStatus::Pending->value);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_note')->nullable();
            $table->timestamps();

            $table->index(['managed_by_user_id', 'status']);
            $table->index('applied_at');
            $table->index('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
