<?php

use App\Models\FundCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', FundCycle::statuses())->default(FundCycle::STATUS_DRAFT);
            $table->unsignedInteger('unit_amount')->default(1000);
            $table->date('start_date');
            $table->date('lock_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('settlement_date')->nullable();
            $table->json('slots')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_cycles');
    }
};
