<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Enums\EventExpenseCategory;
use App\Ledger\Account;
use App\Ledger\Exceptions\ImmutableJournal;
use App\Ledger\Exceptions\UnbalancedEntry;
use App\Ledger\Ledger;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\InvestmentPostings;
use App\Ledger\Postings\MemberPostings;
use App\Ledger\Postings\PlatformPostings;
use App\Models\BusinessTransaction;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\CycleInvestment;
use App\Models\CycleInvestmentCharge;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventIncome;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleTransaction;
use App\Models\GeneralExpense;
use App\Models\JournalEntry;
use Illuminate\Validation\ValidationException;

function taka(int $taka): int
{
    return $taka * 100;
}

test('unbalanced entries are rejected', function () {
    app(Ledger::class)->entry('test', 'broken')
        ->key('broken')
        ->debit(Account::Bank, 100)
        ->credit(Account::MemberBalance, 90, ['user_id' => ledgerAdmin()->id])
        ->post();
})->throws(UnbalancedEntry::class);

test('journal entries are immutable and idempotent', function () {
    $user = ledgerAdmin();
    $ledger = app(Ledger::class);

    $post = fn () => $ledger->entry('test', 'once')
        ->key('only-once')
        ->debit(Account::Bank, 500, ['fund_cycle_id' => null])
        ->credit(Account::MemberBalance, 500, ['user_id' => $user->id])
        ->post();

    $first = $post();
    $second = $post();

    expect($second->id)->toBe($first->id)
        ->and($ledger->creditBalance(Account::MemberBalance, ['user_id' => $user->id]))->toBe(500);

    expect(fn () => $first->update(['description' => 'changed']))->toThrow(ImmutableJournal::class);

    $ledger->reverse($first, 'undo');

    expect($ledger->creditBalance(Account::MemberBalance, ['user_id' => $user->id]))->toBe(0);
});

test('platform may run at a loss but never past the bank', function () {
    $admin = ledgerAdmin();
    $member = ledgerAdmin();
    ledgerVerifiedDeposit($member, 10000);

    $expense = GeneralExpense::query()->create([
        'expense_date' => '2026-01-05',
        'category' => 'sms_charge',
        'amount' => 100,
        'created_by_user_id' => $admin->id,
    ]);

    app(PlatformPostings::class)->expenseRecorded($expense);

    expect(app(Ledger::class)->platformFund())->toBe(-10000)
        ->and(app(Ledger::class)->creditBalance(Account::MemberBalance, ['user_id' => $member->id]))->toBe(1000000);

    $tooBig = GeneralExpense::query()->create([
        'expense_date' => '2026-01-06',
        'category' => 'sms_charge',
        'amount' => 10000,
        'created_by_user_id' => $admin->id,
    ]);

    expect(fn () => app(PlatformPostings::class)->expenseRecorded($tooBig))
        ->toThrow(ValidationException::class);
});

test('full mudaraba cycle from the plan settles to the expected balances', function () {
    $ledger = app(Ledger::class);
    $members = app(MemberPostings::class);
    $investments = app(InvestmentPostings::class);
    $cycles = app(CyclePostings::class);
    $platform = app(PlatformPostings::class);

    $admin = ledgerAdmin();
    $userA = ledgerAdmin();
    $userB = ledgerAdmin();
    $cycle = ledgerCycle($admin);
    $memberA = ledgerMember($userA, 2);
    $memberB = ledgerMember($userB, 3);

    // 1. deposits
    ledgerVerifiedDeposit($userA, 205000);
    ledgerVerifiedDeposit($userB, 305000);

    // 2. registration fee
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();

    foreach ([$memberA, $memberB] as $member) {
        $charge = Charge::query()->create([
            'charge_category_id' => $category->id,
            'member_id' => $member->id,
            'amount' => 5000,
            'status' => Charge::STATUS_POSTED,
            'effective_at' => now(),
        ]);
        $members->chargeSettled(ChargeAllocation::query()->create([
            'charge_id' => $charge->id,
            'amount' => 5000,
            'confirmed_at' => now(),
        ]));
    }

    // 3. allocation
    foreach ([[$memberA, $userA, 200000], [$memberB, $userB, 300000]] as [$member, $user, $amount]) {
        $members->allocateToCycle([
            'fund_cycle_id' => $cycle->id,
            'member_id' => $member->id,
            'slot_key' => 'S1',
            'amount' => $amount,
            'allocated_at' => now(),
            'created_by_user_id' => $admin->id,
        ], $user->id);
    }

    expect($members->availableBalance($userA->id))->toBe(0)
        ->and($investments->cycleCash($cycle->id))->toBe(taka(500000));

    // E1 — event
    $event = ledgerEvent($cycle);
    $eventInvestment = $event->investment;

    $investments->eventWithdrawal(EventBankWithdrawal::query()->create([
        'fund_cycle_event_id' => $event->id, 'withdrawal_date' => '2026-02-01', 'amount' => 150000,
    ]));
    $investments->eventExpense(EventExpense::query()->create([
        'fund_cycle_event_id' => $event->id, 'expense_date' => '2026-02-02',
        'category' => EventExpenseCategory::Procurement, 'paid_from' => 'cash', 'amount' => 140000,
    ]));

    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id, 'order_number' => 'E1-1', 'customer_name' => 'C',
        'customer_phone' => '01700000000', 'status' => 'confirmed', 'total_amount' => 200000, 'advance_amount' => 0,
    ]);

    foreach ([['bkash', 120000], ['cash', 80000]] as [$method, $amount]) {
        $investments->customerPaymentVerified(EventPayment::query()->create([
            'event_order_id' => $order->id, 'amount' => $amount, 'payment_method' => $method,
            'payment_status' => 'verified', 'verified_at' => now(),
        ]));
    }

    $investments->eventIncome(EventIncome::query()->create([
        'fund_cycle_event_id' => $event->id, 'income_date' => '2026-02-10', 'category' => 'scrap_sale',
        'received_via' => 'cash', 'amount' => 2000,
    ]));
    $investments->eventExpense(EventExpense::query()->create([
        'fund_cycle_event_id' => $event->id, 'expense_date' => '2026-02-10',
        'category' => EventExpenseCategory::PaymentFee, 'paid_from' => 'bkash', 'amount' => 1800,
    ]));
    $investments->eventBankDeposit(EventBankDeposit::query()->create([
        'fund_cycle_event_id' => $event->id, 'deposit_date' => '2026-02-11', 'amount' => 118200, 'source' => 'bkash',
    ]));
    $investments->eventBankDeposit(EventBankDeposit::query()->create([
        'fund_cycle_event_id' => $event->id, 'deposit_date' => '2026-02-11', 'amount' => 92000, 'source' => 'cash',
    ]));

    foreach ([['platform_service', 5000], ['asset_rent', 3000]] as [$type, $amount]) {
        $investments->charge(CycleInvestmentCharge::query()->create([
            'cycle_investment_id' => $eventInvestment->id, 'type' => $type, 'amount' => $amount, 'charged_at' => '2026-02-12',
        ]));
    }

    expect($investments->result($eventInvestment))->toBe(taka(52200))
        ->and($investments->closeBlockers($eventInvestment))->toBe([]);

    $investments->close($eventInvestment);

    expect($investments->result($eventInvestment->refresh()))->toBe(taka(52200));

    // B1 — business (no platform use, no charge)
    $business = CycleInvestment::query()->create([
        'fund_cycle_id' => $cycle->id, 'type' => 'business', 'title' => 'Rahim Traders',
    ]);

    foreach ([['invest', 300000], ['profit', 45000], ['capital_return', 300000]] as [$type, $amount]) {
        $investments->businessTransaction(BusinessTransaction::query()->create([
            'cycle_investment_id' => $business->id, 'type' => $type, 'amount' => $amount, 'transaction_date' => '2026-03-01',
        ]));
    }

    $investments->close($business);

    // cycle-level expense
    $cycles->transaction(FundCycleTransaction::query()->create([
        'fund_cycle_id' => $cycle->id, 'direction' => 'expense', 'category' => 'documentation',
        'amount' => 1200, 'transaction_date' => '2026-03-05',
    ]));

    // platform SMS bill
    $platform->expenseRecorded(GeneralExpense::query()->create([
        'expense_date' => '2026-03-06', 'category' => 'sms_charge', 'amount' => 1500,
    ]));

    $summary = $cycles->summary($cycle);

    expect($summary['blockers'])->toBe([])
        ->and($summary['result'])->toBe(taka(96000));

    $cycles->settle($cycle, $admin);

    expect($members->availableBalance($userA->id))->toBe(taka(238400))
        ->and($members->availableBalance($userB->id))->toBe(taka(357600))
        ->and($platform->fund())->toBe(taka(16500))
        ->and($ledger->balance(Account::Bank))->toBe(taka(612500))
        ->and($investments->cycleCash($cycle->id))->toBe(0)
        ->and($ledger->balance(Account::CycleCapital, ['fund_cycle_id' => $cycle->id]))->toBe(0)
        ->and($ledger->balance(Account::CycleResult, ['fund_cycle_id' => $cycle->id]))->toBe(0)
        ->and($cycle->refresh()->status)->toBe(FundCycle::STATUS_SETTLED);

    // whole journal balances
    expect($ledger->balance(Account::cases()))->toBe(0);

    // settled summary still reports the history
    $settled = $cycles->summary($cycle);
    expect($settled['result'])->toBe(taka(96000))
        ->and($settled['members'][0]['payout'])->toBe(taka(357600));

    // settling moves 2030 to members; each project's result must survive it
    expect($investments->result($eventInvestment))->toBe(taka(52200))
        ->and($investments->result($business->refresh()))->toBe(taka(45000));

    expect(JournalEntry::query()->where('kind', 'cycle_settled')->count())->toBe(1);
});

test('a loss is borne by members in capital ratio', function () {
    $members = app(MemberPostings::class);
    $investments = app(InvestmentPostings::class);
    $cycles = app(CyclePostings::class);

    $admin = ledgerAdmin();
    $userA = ledgerAdmin();
    $userB = ledgerAdmin();
    $cycle = ledgerCycle($admin);

    foreach ([[$userA, 2, 200000], [$userB, 3, 300000]] as [$user, $units, $amount]) {
        $member = ledgerMember($user, $units);
        ledgerVerifiedDeposit($user, $amount);
        $members->allocateToCycle([
            'fund_cycle_id' => $cycle->id, 'member_id' => $member->id, 'slot_key' => 'S1',
            'amount' => $amount, 'allocated_at' => now(), 'created_by_user_id' => $admin->id,
        ], $user->id);
    }

    $business = CycleInvestment::query()->create(['fund_cycle_id' => $cycle->id, 'type' => 'business', 'title' => 'B']);

    foreach ([['invest', 300000], ['capital_return', 260000], ['capital_loss', 40000]] as [$type, $amount]) {
        $investments->businessTransaction(BusinessTransaction::query()->create([
            'cycle_investment_id' => $business->id, 'type' => $type, 'amount' => $amount, 'transaction_date' => '2026-03-01',
        ]));
    }

    $investments->close($business);
    $cycles->settle($cycle, $admin);

    expect($members->availableBalance($userA->id))->toBe(taka(184000))
        ->and($members->availableBalance($userB->id))->toBe(taka(276000))
        ->and($investments->result($business->refresh()))->toBe(taka(-40000));
});

test('closing is blocked while event money is outside the bank', function () {
    $investments = app(InvestmentPostings::class);
    $admin = ledgerAdmin();
    $user = ledgerAdmin();
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    ledgerVerifiedDeposit($user, 100000);
    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id, 'member_id' => $member->id, 'slot_key' => 'S1',
        'amount' => 100000, 'allocated_at' => now(), 'created_by_user_id' => $admin->id,
    ], $user->id);

    $event = ledgerEvent($cycle);
    $investments->eventWithdrawal(EventBankWithdrawal::query()->create([
        'fund_cycle_event_id' => $event->id, 'withdrawal_date' => '2026-02-01', 'amount' => 1000,
    ]));

    expect($investments->closeBlockers($event->investment))->toHaveCount(1);
    expect(fn () => $investments->close($event->investment))->toThrow(ValidationException::class);
});

test('withdrawals cannot exceed the cycle bank money', function () {
    $admin = ledgerAdmin();
    $cycle = ledgerCycle($admin);
    $event = ledgerEvent($cycle);

    expect(fn () => app(InvestmentPostings::class)->eventWithdrawal(EventBankWithdrawal::query()->create([
        'fund_cycle_event_id' => $event->id, 'withdrawal_date' => '2026-02-01', 'amount' => 1,
    ])))->toThrow(ValidationException::class);
});

test('ledger:check passes on a healthy journal and fails on a negative balance', function () {
    $user = ledgerAdmin();
    ledgerVerifiedDeposit($user, 1000);

    $this->artisan('ledger:check')->assertSuccessful();

    app(Ledger::class)->entry('test', 'overdraw')
        ->key('overdraw')
        ->debit(Account::MemberBalance, taka(2000), ['user_id' => $user->id])
        ->credit(Account::Bank, taka(2000), ['fund_cycle_id' => null])
        ->post();

    $this->artisan('ledger:check')->assertFailed();
});
