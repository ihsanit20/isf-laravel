<?php

use App\Enums\DepositSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('payment_method', 30);
            $table->string('reference_no', 100)->nullable();
            $table->date('deposit_date');
            $table->string('proof_path');
            $table->string('notes')->nullable();
            $table->enum('status', DepositSubmissionStatus::values())->default(DepositSubmissionStatus::Pending->value);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('deposit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_submissions');
    }
};
