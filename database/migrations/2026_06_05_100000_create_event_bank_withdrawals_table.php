<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_bank_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained('fund_cycle_events')->cascadeOnDelete();
            $table->date('withdrawal_date');
            $table->unsignedInteger('amount');
            $table->text('description')->nullable();
            $table->string('reference_no')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fund_cycle_event_id', 'withdrawal_date']);
            $table->index('reference_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bank_withdrawals');
    }
};
