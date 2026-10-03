<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_cycle_events', function (Blueprint $table) {
            $table->foreignId('cycle_investment_id')
                ->nullable()
                ->after('fund_cycle_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('fund_cycles', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable()->after('settlement_date');
            $table->foreignId('settled_by_user_id')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('event_expenses', function (Blueprint $table) {
            $table->enum('paid_from', ['cash', 'bkash', 'bank'])->default('cash')->after('category');
        });

        Schema::table('event_bank_deposits', function (Blueprint $table) {
            $table->enum('source', ['cash', 'bkash'])->default('cash')->after('amount');
        });

        Schema::table('general_expenses', function (Blueprint $table) {
            $table->string('category', 50)->change();
        });

        Schema::table('general_incomes', function (Blueprint $table) {
            $table->string('category', 50)->change();
        });

        Schema::create('cycle_investment_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_investment_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['platform_service', 'asset_rent', 'other']);
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->date('charged_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_event_id')->constrained()->restrictOnDelete();
            $table->date('income_date');
            $table->enum('category', ['scrap_sale', 'sponsorship', 'other']);
            $table->enum('received_via', ['cash', 'bkash', 'bank']);
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_investment_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['invest', 'profit', 'capital_return', 'capital_loss', 'other_income', 'expense']);
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('reference_no', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fund_cycle_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_id')->constrained()->restrictOnDelete();
            $table->enum('direction', ['income', 'expense']);
            $table->string('category', 50);
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'bkash', 'bank']);
            $table->string('reference_no', 120)->nullable();
            $table->text('note')->nullable();
            $table->date('refunded_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('account_details')->nullable();
            $table->string('notes')->nullable();
            $table->enum('status', ['pending', 'paid', 'rejected'])->default('pending');
            $table->string('reference_no', 120)->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('event_refunds');
        Schema::dropIfExists('fund_cycle_transactions');
        Schema::dropIfExists('business_transactions');
        Schema::dropIfExists('event_incomes');
        Schema::dropIfExists('cycle_investment_charges');

        Schema::table('event_bank_deposits', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('event_expenses', function (Blueprint $table) {
            $table->dropColumn('paid_from');
        });

        Schema::table('fund_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by_user_id');
            $table->dropColumn('settled_at');
        });

        Schema::table('fund_cycle_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_investment_id');
        });
    }
};
