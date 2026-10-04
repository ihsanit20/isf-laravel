<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Enums\DepositSubmissionStatus;
use App\Enums\GeneralExpenseCategory;
use App\Enums\GeneralIncomeCategory;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Models\DepositSubmission;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventIncome;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use App\Models\GeneralExpense;
use App\Models\GeneralIncome;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Documents as they existed before the journal: model events are muted so
 * nothing is posted on create.
 */
function legacyBooks(int $withdrawal = 3000): FundCycleEvent
{
    $admin = ledgerAdmin();
    $user = User::factory()->create();
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    $event = ledgerEvent($cycle);

    Model::withoutEvents(function () use ($admin, $user, $cycle, $member): void {
        DepositSubmission::query()->create([
            'user_id' => $user->id,
            'amount' => 10000,
            'payment_method' => DepositSubmission::PAYMENT_METHOD_BANK_TRANSFER,
            'deposit_date' => '2026-01-01',
            'proof_path' => 'proofs/x.png',
            'status' => DepositSubmissionStatus::Verified,
            'verified_at' => '2026-01-01 10:00:00',
        ]);

        FundCycleAllocation::query()->create([
            'fund_cycle_id' => $cycle->id,
            'member_id' => $member->id,
            'slot_key' => 'S1',
            'amount' => 6000,
            'allocated_at' => '2026-01-05 10:00:00',
            'created_by_user_id' => $admin->id,
        ]);
    });

    GeneralIncome::query()->create(['income_date' => '2026-01-02', 'category' => GeneralIncomeCategory::Sponsorship, 'amount' => 500]);
    GeneralExpense::query()->create(['expense_date' => '2026-01-03', 'category' => GeneralExpenseCategory::OfficeSupplies, 'amount' => 800]);

    EventBankWithdrawal::query()->create(['fund_cycle_event_id' => $event->id, 'withdrawal_date' => '2026-01-10', 'amount' => $withdrawal]);
    EventExpense::query()->create(['fund_cycle_event_id' => $event->id, 'expense_date' => '2026-01-11', 'category' => 'procurement', 'paid_from' => 'cash', 'amount' => 2500]);

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id, 'order_number' => 'L-1', 'customer_name' => 'C',
        'customer_phone' => '01700000001', 'status' => 'delivered', 'total_amount' => 1600, 'advance_amount' => 1000,
    ]);
    EventPayment::query()->create([
        'event_order_id' => $order->id, 'amount' => 1000, 'payment_type' => 'advance', 'payment_method' => 'bkash',
        'payment_status' => 'verified', 'verified_at' => '2026-01-12 10:00:00',
    ]);
    EventPayment::query()->create([
        'event_order_id' => $order->id, 'amount' => 600, 'payment_type' => 'manual', 'payment_method' => 'cash',
        'payment_status' => 'verified', 'verified_at' => '2026-01-12 11:00:00',
    ]);

    // The old system did not split cash and bKash: 2,300 deposited covers both.
    EventBankDeposit::query()->create(['fund_cycle_event_id' => $event->id, 'deposit_date' => '2026-01-13', 'amount' => 2300]);

    $event->forceFill(['is_finalized' => true])->saveQuietly();

    return $event;
}

test('backfill posts legacy documents and closes finalized events', function () {
    $event = legacyBooks();
    $ledger = app(Ledger::class);

    expect(JournalEntry::query()->count())->toBe(0);

    $this->artisan('ledger:backfill')->assertSuccessful();

    $investment = $event->fresh()->investment;

    expect($investment->isClosed())->toBeTrue()
        // bank deposits − withdrawals, as the old system reported it
        ->and($ledger->creditBalance(Account::CycleResult, $investment->dimensions()))->toBe(-70000)
        ->and(EventIncome::query()->sole()->amount)->toEqual(200)
        ->and($ledger->balance(Account::Bkash))->toBe(0)
        ->and($ledger->balance(Account::EventCash))->toBe(0)
        ->and($ledger->creditBalance(Account::MemberBalance))->toBe(400000)
        ->and($ledger->platformFund())->toBe(-30000)
        ->and($ledger->balance(Account::Bank))->toBe(900000)
        ->and($ledger->balance(Account::cases()))->toBe(0);

    $entries = JournalEntry::query()->count();

    $this->artisan('ledger:backfill')->assertSuccessful();

    expect(JournalEntry::query()->count())->toBe($entries);
});

test('dry run reports without saving', function () {
    legacyBooks();

    $this->artisan('ledger:backfill', ['--dry-run' => true])->assertSuccessful();

    expect(JournalEntry::query()->count())->toBe(0)
        ->and(EventIncome::query()->count())->toBe(0);
});

test('backfill refuses history that overspends a cycle and saves nothing', function () {
    legacyBooks(withdrawal: 7000);

    $this->artisan('ledger:backfill')
        ->expectsOutputToContain('Not enough cycle money in the bank')
        ->assertFailed();

    expect(JournalEntry::query()->count())->toBe(0);
});
