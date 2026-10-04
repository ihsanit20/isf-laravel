<?php

namespace App\Http\Controllers;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\FundCycle;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's wallet: every movement of their available balance with a running
 * total, what is currently invested in each cycle, and their withdrawal
 * requests.
 */
class WalletController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        /** @var User $user */
        $user = $request->user();

        $running = 0;
        $lines = JournalLine::query()
            ->with(['entry:id,entry_date,kind,description', 'member:id,full_name'])
            ->where('ledger_account_id', LedgerAccount::idFor(Account::MemberBalance))
            ->where('user_id', $user->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get()
            ->map(function (JournalLine $line) use (&$running): array {
                $amount = $line->credit - $line->debit;
                $running += $amount;

                return [
                    'id' => $line->id,
                    'date' => $line->entry?->entry_date?->format('Y-m-d'),
                    'kind' => $line->entry?->kind,
                    'description' => $line->entry?->description,
                    'member' => $line->member?->full_name,
                    'credit' => $amount > 0 ? Money::toTaka($amount) : 0,
                    'debit' => $amount < 0 ? Money::toTaka(-$amount) : 0,
                    'balance' => Money::toTaka($running),
                ];
            });

        $capital = $ledger->balancesBy('fund_cycle_id', Account::CycleCapital, ['user_id' => $user->id])
            ->map(fn (int $balance): int => -$balance)
            ->filter();
        $cycles = FundCycle::query()->whereIn('id', $capital->keys())->get(['id', 'name', 'status'])->keyBy('id');

        $payouts = PayoutRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $available = $ledger->creditBalance(Account::MemberBalance, ['user_id' => $user->id]);
        $pending = Money::toPaisa($payouts->where('status', PayoutRequest::STATUS_PENDING)->sum('amount'));

        return Inertia::render('Wallet', [
            'availableBalance' => Money::toTaka($available),
            'investedCapital' => Money::toTaka((int) $capital->sum()),
            'investments' => $capital
                ->map(fn (int $amount, int|string $cycleId): array => [
                    'cycle_id' => (int) $cycleId,
                    'cycle_name' => $cycles->get((int) $cycleId)?->name,
                    'status' => $cycles->get((int) $cycleId)?->status,
                    'amount' => Money::toTaka($amount),
                ])
                ->values(),
            'lines' => $lines->reverse()->values(),
            'payoutSummary' => [
                'pending_amount' => Money::toTaka($pending),
                'requestable_amount' => Money::toTaka(max(0, $available - $pending)),
            ],
            'paymentMethods' => collect(PayoutRequest::PAYMENT_METHODS)
                ->map(fn (string $method): array => ['value' => $method, 'label' => str($method)->replace('_', ' ')->title()->toString()])
                ->values(),
            'payouts' => $payouts
                ->map(fn (PayoutRequest $payout): array => PayoutController::transform($payout))
                ->values(),
        ]);
    }
}
