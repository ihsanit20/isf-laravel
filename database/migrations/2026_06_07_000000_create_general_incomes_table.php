<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_incomes', function (Blueprint $table) {
            $table->id();
            $table->date('income_date');
            $table->enum('category', [
                'platform_fee',
                'sponsorship',
                'sale_proceeds',
                'rental_income',
                'other',
            ]);
            $table->unsignedInteger('amount');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['income_date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_incomes');
    }
};
