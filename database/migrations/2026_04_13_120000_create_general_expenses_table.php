<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->enum('category', [
                'office_supplies',
                'printing',
                'bank_charge',
                'it_expense',
                'transport',
                'refreshment',
                'utility',
                'service_fee',
                'other',
            ]);
            $table->unsignedInteger('amount');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_expenses');
    }
};
