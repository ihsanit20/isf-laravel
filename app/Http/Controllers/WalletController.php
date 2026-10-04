<?php

namespace App\Http\Controllers;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\FundCycle;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's own statement: every movement of their available balance with a
 * running total, plus what is currently invested in each cycle.
 */
class StatementController extends Controller
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

        return Inertia::render('Statement', [
            'availableBalance' => Money::toTaka($ledger->creditBalance(Account::MemberBalance, ['user_id' => $user->id])),
            'investedCapital' => Money::toTaka((int) $capital->sum()),
            'investments' => $capital
                ->map(fn (int $amount, int|string $cycleId): array => [
                    'cycle_id' => (int) $cycleId,
                    'cycle_name' => $cycles->get((int) $cycleId)?->name,
                    'status' => $cycles->get((int) $cycleId)?->status,
                    'amount' => Money::toTaka($amount),
                ])
                ->values(),
            'members' => Member::query()->where('managed_by_user_id', $user->id)->pluck('full_name', 'id'),
            'lines' => $lines->reverse()->values(),
        ]);
    }
}
