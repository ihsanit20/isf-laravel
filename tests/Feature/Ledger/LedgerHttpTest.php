<?php

require_once __DIR__.'/LedgerTestHelpers.php';

use App\Enums\MemberStatus;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Postings\InvestmentPostings;
use App\Ledger\Postings\MemberPostings;
use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\CycleInvestment;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\Member;
use App\Models\PayoutRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Admin, a cycle with one member holding `$capital` taka, and that member's user.
 *
 * @return array{admin: User, cycle: FundCycle, user: User, member: Member}
 */
function fundedCycle(int $capital = 100000, int $extraDeposit = 0): array
{
    $admin = ledgerAdmin();
    $user = User::factory()->create();
    $cycle = ledgerCycle($admin);
    $member = ledgerMember($user, 1);
    ledgerVerifiedDeposit($user, $capital + $extraDeposit);

    app(MemberPostings::class)->allocateToCycle([
        'fund_cycle_id' => $cycle->id,
        'member_id' => $member->id,
        'slot_key' => 'S1',
        'amount' => $capital,
        'allocated_at' => now(),
        'created_by_user_id' => $admin->id,
    ], $user->id);

    return compact('admin', 'cycle', 'user', 'member');
}

test('event accounts: other income, charges and finalize through the admin UI', function () {
    ['admin' => $admin, 'cycle' => $cycle] = fundedCycle();
    $event = ledgerEvent($cycle);
    $investment = $event->investment;

    actingAs($admin);

    $this->post(route('admin.events.bank-withdrawals.store', $event), [
        'withdrawal_date' => '2026-02-01', 'amount' => 10000,
    ])->assertSessionHasNoErrors();

    $this->post(route('admin.events.incomes.store', $event), [
        'income_date' => '2026-02-02', 'category' => 'scrap_sale', 'received_via' => 'cash', 'amount' => 500,
    ])->assertSessionHasNoErrors();

    $this->post(route('admin.events.expenses.store', $event), [
        'expense_date' => '2026-02-02', 'category' => 'procurement', 'paid_from' => 'cash', 'amount' => 9000,
    ])->assertSessionHasNoErrors();

    // still 1,500 cash in hand: finalize is blocked
    $this->patch(route('admin.events.finalize', $event))->assertSessionHasErrors('close');
    expect($event->refresh()->is_finalized)->toBeFalse();

    $this->post(route('admin.events.bank-deposits.store', $event), [
        'deposit_date' => '2026-02-03', 'amount' => 1500, 'source' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->post(route('admin.investments.charges.store', $investment), [
        'type' => 'asset_rent', 'amount' => 200, 'charged_at' => '2026-02-03',
    ])->assertSessionHasNoErrors();

    $this->get(route('admin.events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/EventDetails')
            ->where('ledger.result', -8700)
            ->where('ledger.close_blockers', [])
            ->has('event.incomes', 1)
            ->has('ledger.charges', 1));

    $this->patch(route('admin.events.finalize', $event))->assertSessionHasNoErrors();

    expect($event->refresh()->is_finalized)->toBeTrue()
        ->and($investment->refresh()->status)->toBe(CycleInvestment::STATUS_CLOSED)
        ->and(app(Ledger::class)->creditBalance(Account::CycleResult, ['cycle_investment_id' => $investment->id]))->toBe(-870000)
        ->and(app(Ledger::class)->platformFund())->toBe(20000);

    // locked after finalize
    $this->post(route('admin.investments.charges.store', $investment), [
        'type' => 'asset_rent', 'amount' => 100, 'charged_at' => '2026-02-04',
    ])->assertForbidden();
});

test('business investment lifecycle and cycle settlement through the admin UI', function () {
    ['admin' => $admin, 'cycle' => $cycle, 'user' => $user] = fundedCycle(100000);

    actingAs($admin);

    $this->post(route('admin.businesses.store'), [
        'fund_cycle_id' => $cycle->id, 'title' => 'Rahim Traders', 'counterparty' => 'Rahim',
    ])->assertRedirect();

    $business = CycleInvestment::query()->where('type', 'business')->firstOrFail();

    foreach ([['invest', 80000], ['profit', 6000], ['capital_return', 80000]] as [$type, $amount]) {
        $this->post(route('admin.businesses.transactions.store', $business), [
            'type' => $type, 'amount' => $amount, 'transaction_date' => '2026-03-01',
        ])->assertSessionHasNoErrors();
    }

    // returning more than invested is refused
    $this->post(route('admin.businesses.transactions.store', $business), [
        'type' => 'capital_return', 'amount' => 1, 'transaction_date' => '2026-03-02',
    ])->assertSessionHasErrors('amount');

    $this->get(route('admin.businesses.show', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BusinessInvestmentDetails')
            ->where('ledger.result', 6000)
            ->has('transactions', 3));

    $this->patch(route('admin.businesses.close', $business))->assertSessionHasNoErrors();

    $this->post(route('admin.fund-cycles.transactions.store', $cycle), [
        'direction' => 'expense', 'category' => 'documentation', 'amount' => 1000, 'transaction_date' => '2026-03-05',
    ])->assertSessionHasNoErrors();

    $this->get(route('admin.fund-cycles.show', $cycle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ledger.result', 5000)
            ->where('ledger.blockers', [])
            ->where('ledger.members.0.payout', 105000));

    $this->post(route('admin.fund-cycles.settle', $cycle))->assertSessionHasNoErrors();

    expect($cycle->refresh()->isSettled())->toBeTrue()
        ->and(app(MemberPostings::class)->availableBalance($user->id))->toBe(10500000);

    // settled cycle is locked
    $this->post(route('admin.fund-cycles.transactions.store', $cycle), [
        'direction' => 'income', 'category' => 'other', 'amount' => 1, 'transaction_date' => '2026-03-06',
    ])->assertForbidden();

    actingAs($user)
        ->get(route('fund-cycles.show', $cycle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycleResult.is_settled', true)
            ->where('cycleResult.my_share', 5000)
            ->where('cycleResult.my_payout', 105000));
});

test('settlement is blocked while a project is still open', function () {
    ['admin' => $admin, 'cycle' => $cycle] = fundedCycle();
    ledgerEvent($cycle);

    actingAs($admin)
        ->post(route('admin.fund-cycles.settle', $cycle))
        ->assertSessionHasErrors('settle');

    expect($cycle->refresh()->isSettled())->toBeFalse();
});

test('users request payouts within their balance and admins pay them', function () {
    $admin = ledgerAdmin();
    $user = User::factory()->create();
    ledgerVerifiedDeposit($user, 5000);

    actingAs($user)
        ->post(route('payouts.store'), ['amount' => 6000, 'payment_method' => 'bank_transfer'])
        ->assertSessionHasErrors('amount');

    actingAs($user)
        ->post(route('payouts.store'), ['amount' => 3000, 'payment_method' => 'mobile_banking', 'account_details' => '01700000000'])
        ->assertSessionHasNoErrors();

    // pending requests count against the next request
    actingAs($user)
        ->post(route('payouts.store'), ['amount' => 2500, 'payment_method' => 'bank_transfer'])
        ->assertSessionHasErrors('amount');

    $payout = PayoutRequest::query()->firstOrFail();

    actingAs($admin)
        ->patch(route('admin.payouts.review', $payout), ['status' => 'paid', 'reference_no' => 'TRX-1'])
        ->assertSessionHasNoErrors();

    expect($payout->refresh()->status)->toBe(PayoutRequest::STATUS_PAID)
        ->and(app(MemberPostings::class)->availableBalance($user->id))->toBe(200000);

    actingAs($user)
        ->get(route('statement.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Statement')
            ->where('availableBalance', 2000)
            ->has('lines', 2));
});

test('charges cannot be paid with money already allocated to a cycle (B1)', function () {
    ['user' => $user, 'member' => $member] = fundedCycle(100000);
    $category = ChargeCategory::query()->where('code', ChargeCategory::CODE_REGISTRATION_FEE)->firstOrFail();
    $charge = Charge::query()->create([
        'charge_category_id' => $category->id,
        'member_id' => $member->id,
        'amount' => 500,
        'status' => Charge::STATUS_PENDING,
        'effective_at' => now(),
    ]);

    actingAs($user)
        ->post(route('deposits.allocations.store'), ['charge_ids' => [$charge->id]])
        ->assertSessionHasErrors('charge_ids');

    expect($charge->refresh()->status)->toBe(Charge::STATUS_PENDING);
});

test('admin allocation uses the member manager\'s own balance (B2)', function () {
    $admin = ledgerAdmin();
    $cycle = ledgerCycle($admin, ['unit_amount' => 1000]);
    $richUser = User::factory()->create();
    ledgerVerifiedDeposit($richUser, 50000);
    $poorMember = ledgerMember(User::factory()->create(), 1);

    actingAs($admin)
        ->post(route('admin.fund-cycles.allocations.store', $cycle), [
            'member_id' => $poorMember->id,
            'slot_key' => 'S1',
        ])
        ->assertSessionHasErrors('member_id');
});

test('members with capital in an unsettled cycle cannot be exited', function () {
    ['admin' => $admin, 'member' => $member] = fundedCycle();

    actingAs($admin)
        ->patch(route('admin.members.review', $member), ['status' => MemberStatus::Exited->value])
        ->assertSessionHasErrors('status');
});

test('refunds are capped by what the customer paid and reduce event sales', function () {
    ['admin' => $admin, 'cycle' => $cycle] = fundedCycle();
    $event = ledgerEvent($cycle);
    $order = EventOrder::query()->create([
        'fund_cycle_event_id' => $event->id, 'order_number' => 'R-1', 'customer_name' => 'C',
        'customer_phone' => '01700000001', 'status' => 'cancelled', 'total_amount' => 1000, 'advance_amount' => 300,
    ]);
    app(InvestmentPostings::class)->customerPaymentVerified(EventPayment::query()->create([
        'event_order_id' => $order->id, 'amount' => 300, 'payment_method' => 'bkash',
        'payment_status' => 'verified', 'verified_at' => now(),
    ]));

    actingAs($admin);

    $this->post(route('admin.events.orders.refunds.store', [$event, $order]), [
        'amount' => 400, 'method' => 'bkash', 'refunded_at' => '2026-02-05',
    ])->assertSessionHasErrors('amount');

    $this->post(route('admin.events.orders.refunds.store', [$event, $order]), [
        'amount' => 300, 'method' => 'bkash', 'refunded_at' => '2026-02-05',
    ])->assertSessionHasNoErrors();

    expect(app(InvestmentPostings::class)->result($event->investment))->toBe(0)
        ->and(app(InvestmentPostings::class)->bkash($event->investment))->toBe(0);
});

test('accounts and journal pages render balanced figures', function () {
    ['admin' => $admin] = fundedCycle(100000, 5000);

    actingAs($admin)
        ->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Accounts')
            ->where('trialTotals.debit', 105000)
            ->where('trialTotals.credit', 105000)
            ->where('treasury.members_available', 5000)
            ->where('treasury.cycle_capital', 100000));

    actingAs($admin)
        ->get(route('admin.accounts.journal', ['account' => Account::CycleCapital->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Journal')
            ->has('entries.data', 1));
});
