<?php

use App\Ledger\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->enum('scope', ['fund', 'platform'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cycle_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_cycle_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['event', 'business']);
            $table->string('title');
            $table->string('counterparty')->nullable();
            $table->text('terms')->nullable();
            $table->date('invested_at')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fund_cycle_id', 'type', 'status']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('kind', 50);
            $table->string('description');
            $table->nullableMorphs('source');
            $table->string('idempotency_key')->unique();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['kind', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fund_cycle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cycle_investment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('event_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'user_id']);
            $table->index(['ledger_account_id', 'member_id']);
            $table->index(['ledger_account_id', 'fund_cycle_id']);
            $table->index(['ledger_account_id', 'cycle_investment_id']);
        });

        Schema::create('ledger_locks', function (Blueprint $table) {
            $table->string('key', 100)->primary();
        });

        $now = now();

        DB::table('ledger_accounts')->insert(array_map(fn (Account $account): array => [
            'code' => $account->value,
            'name' => $account->label(),
            'type' => $account->type(),
            'scope' => $account->scope(),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], Account::cases()));
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_locks');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cycle_investments');
        Schema::dropIfExists('ledger_accounts');
    }
};
