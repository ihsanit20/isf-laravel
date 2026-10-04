<?php

namespace App\Console\Commands;

use App\Enums\DepositSubmissionStatus;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\ChargeAllocation;
use App\Models\CycleInvestment;
use App\Models\DepositSubmission;
use App\Models\EventPayment;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayoutRequest;
use Illuminate\Console\Command;

/**
 * Integrity checks over the journal. Exit code 1 when anything is off.
 */
class LedgerCheck extends Command
{
    protected $signature = 'ledger:check';

    protected $description = 'Verify the double-entry journal: balanced entries, no negative balances, closed books empty, source documents posted';

    public function handle(Ledger $ledger): int
    {
        $problems = [];

        $unbalanced = JournalLine::query()
            ->selectRaw('journal_entry_id, SUM(debit) as d, SUM(credit) as c')
            ->groupBy('journal_entry_id')
            ->havingRaw('SUM(debit) <> SUM(credit)')
            ->pluck('journal_entry_id');

        if ($unbalanced->isNotEmpty()) {
            $problems[] = 'Unbalanced journal entries: #'.$unbalanced->implode(', #');
        }

        if (($total = $ledger->balance(Account::cases())) !== 0) {
            $problems[] = 'Trial balance is off by '.Money::format($total).' BDT.';
        }

        // A negative platform fund (a platform loss) or general bank share
        // (platform spent cycle-earmarked money) is reported, not an error.
        $platform = $ledger->platformFund();
        $generalBank = $ledger->balance(Account::Bank, ['fund_cycle_id' => null]);

        $ledger->balancesBy('user_id', Account::MemberBalance)
            ->filter(fn (int $balance, $userId): bool => $userId !== null && $userId !== '' && $balance > 0)
            ->each(function (int $balance, $userId) use (&$problems): void {
                $problems[] = "User #{$userId} has a negative available balance: ".Money::format(-$balance).' BDT.';
            });

        FundCycle::query()->get(['id', 'name'])->each(function (FundCycle $cycle) use ($ledger, &$problems): void {
            $cash = $ledger->balance(Account::Bank, ['fund_cycle_id' => $cycle->id]);

            if ($cash < 0) {
                $problems[] = "Cycle \"{$cycle->name}\" bank share is negative: ".Money::format($cash).' BDT.';
            }
        });

        if (($bank = $ledger->balance(Account::Bank)) < 0) {
            $problems[] = 'Joint bank balance is negative: '.Money::format($bank).' BDT.';
        }

        $this->checkInvestments($ledger, $problems);
        $this->checkSettledCycles($ledger, $problems);
        $this->checkSourceDocuments($problems);

        if ($problems === []) {
            $this->info('Journal OK. Joint bank: '.Money::format($bank).' BDT, platform fund: '.Money::format($platform).' BDT.');

            if ($platform < 0) {
                $this->warn('Platform fund is negative: the platform is running at a loss.');
            }

            if ($generalBank < 0) {
                $this->warn('Bank share outside fund cycles is negative ('.Money::format($generalBank).' BDT): cycle-earmarked money is covering it.');
            }

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        logger()->error('ledger:check found problems', $problems);

        return self::FAILURE;
    }

    /**
     * Money held by a sub-business can't be negative, and a closed or
     * cancelled one must hold nothing and have no unclosed income/expense.
     *
     * @param  list<string>  $problems
     */
    private function checkInvestments(Ledger $ledger, array &$problems): void
    {
        $investments = CycleInvestment::query()->get(['id', 'title', 'status'])->keyBy('id');

        $holdings = [
            'bKash' => Account::Bkash,
            'event cash' => Account::EventCash,
            'invested capital' => Account::BusinessInvestment,
        ];

        foreach ($holdings as $label => $account) {
            $ledger->balancesBy('cycle_investment_id', $account)
                ->each(function (int $balance, $investmentId) use ($investments, $label, &$problems): void {
                    $investment = $investments->get((int) $investmentId);

                    if ($investment === null || $balance === 0) {
                        return;
                    }

                    if ($balance < 0) {
                        $problems[] = "Investment \"{$investment->title}\" {$label} is negative: ".Money::format($balance).' BDT.';
                    } elseif (! $investment->isActive()) {
                        $problems[] = "Investment \"{$investment->title}\" is {$investment->status} but still holds {$label}: ".Money::format($balance).' BDT.';
                    }
                });
        }

        $ledger->balancesBy('cycle_investment_id', Account::fundProfitAndLoss())
            ->each(function (int $balance, $investmentId) use ($investments, &$problems): void {
                $investment = $investments->get((int) $investmentId);

                if ($investment !== null && $balance !== 0 && ! $investment->isActive()) {
                    $problems[] = "Investment \"{$investment->title}\" is {$investment->status} but has unclosed income/expense: ".Money::format(-$balance).' BDT.';
                }
            });
    }

    /**
     * A settled cycle has returned everything: no capital, result, earmarked
     * bank money, deployed money or open income/expense may remain.
     *
     * @param  list<string>  $problems
     */
    private function checkSettledCycles(Ledger $ledger, array &$problems): void
    {
        FundCycle::query()->whereNotNull('settled_at')->get(['id', 'name'])
            ->each(function (FundCycle $cycle) use ($ledger, &$problems): void {
                $dims = ['fund_cycle_id' => $cycle->id];

                $leftovers = [
                    'member capital' => $ledger->balance(Account::CycleCapital, $dims),
                    'undistributed result' => $ledger->balance(Account::CycleResult, $dims),
                    'bank share' => $ledger->balance(Account::Bank, $dims),
                    'deployed money' => $ledger->balance([Account::Bkash, Account::EventCash, Account::BusinessInvestment], $dims),
                    'income/expense' => $ledger->balance(Account::fundProfitAndLoss(), $dims),
                ];

                foreach (array_filter($leftovers) as $label => $balance) {
                    $problems[] = "Settled cycle \"{$cycle->name}\" still has {$label}: ".Money::format(abs($balance)).' BDT.';
                }
            });
    }

    /**
     * Every source document in a money-moving state has a live journal entry,
     * and none outside that state has one.
     *
     * @param  list<string>  $problems
     */
    private function checkSourceDocuments(array &$problems): void
    {
        $documents = [
            'Verified deposit' => [
                DepositSubmission::query()->where('status', DepositSubmissionStatus::Verified),
                DepositSubmission::query()->where('status', '!=', DepositSubmissionStatus::Verified),
            ],
            'Verified customer payment' => [
                EventPayment::query()->where('payment_status', 'verified'),
                EventPayment::query()->where('payment_status', '!=', 'verified'),
            ],
            'Paid payout' => [
                PayoutRequest::query()->where('status', PayoutRequest::STATUS_PAID),
                PayoutRequest::query()->where('status', '!=', PayoutRequest::STATUS_PAID),
            ],
            'Charge settlement' => [
                ChargeAllocation::query()->whereNull('reversed_at'),
                ChargeAllocation::query()->whereNotNull('reversed_at'),
            ],
            'Cycle allocation' => [FundCycleAllocation::query(), null],
        ];

        foreach ($documents as $label => [$posted, $unposted]) {
            $postedIds = JournalEntry::query()
                ->where('source_type', $posted->getModel()->getMorphClass())
                ->whereNull('reversal_of_id')
                ->whereDoesntHave('reversal')
                ->select('source_id');

            $missing = (clone $posted)->whereNotIn('id', $postedIds)->pluck('id');

            if ($missing->isNotEmpty()) {
                $problems[] = "{$label} without a journal entry: #".$missing->implode(', #');
            }

            $extra = $unposted?->whereIn('id', $postedIds)->pluck('id') ?? collect();

            if ($extra->isNotEmpty()) {
                $problems[] = "{$label} has a live journal entry but is not in a posted state: #".$extra->implode(', #');
            }
        }
    }
}
