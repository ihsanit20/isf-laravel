<?php

use App\Enums\EventExpenseCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained('fund_cycle_events')->cascadeOnDelete();
            $table->date('expense_date');
            $table->enum('category', EventExpenseCategory::values());
            $table->unsignedInteger('amount');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fund_cycle_event_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_expenses');
    }
};
