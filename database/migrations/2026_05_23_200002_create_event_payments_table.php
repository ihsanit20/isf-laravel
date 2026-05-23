<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_order_id')->constrained('event_orders')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->nullable(); // bkash, nagad, cash, etc.
            // status: pending | verified | rejected
            $table->string('payment_status', 20)->default('pending');
            $table->string('transaction_reference', 100)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['event_order_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payments');
    }
};
