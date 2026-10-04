<?php

namespace App\Console\Commands;

use App\Enums\DepositSubmissionStatus;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\CyclePostings;
use App\Ledger\Postings\InvestmentPostings;
use App\Ledger\Postings\MemberPostings;
use App\Ledger\Postings\PlatformPostings;
use App\Models\BusinessTransaction;
use App\Models\ChargeAllocation;
use App\Models\CycleInvestmentCharge;
use App\Models\DepositSubmission;
use App\Models\EventBankDeposit;
use App\Models\EventBankWithdrawal;
use App\Models\EventExpense;
use App\Models\EventIncome;
use App\Models\EventPayment;
use App\Models\EventRefund;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\FundCycleEvent;
use App\Models\FundCycleTransaction;
use App\Models\GeneralExpense;
use App\Models\GeneralIncome;
use App\Models\JournalEntry;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts journal entries for documents recorded before the journal existed.
 *
 * Documents are replayed in date order with every balance check active, so a
 * historical record that would overspend fails the run instead of slipping in.
 * Already-posted documents are skipped, so the command is safe to re-run.
 */
class LedgerBackfill extends Command
{
    protected $signature = 'ledger:backfill
        {--dry-run : Post everything, print the report, then roll back}';

    protected $description = 'Post journal entries for money documents created before the journal existed';

    /** @var array<string, true> */
    private array $postedSources = [];

    /** @var array<int, User|null> */
    private array $users = [];

    public function __construct(
        private readonly Ledger $ledger,
        private readonly MemberPostings $members,
        private readonly PlatformPostings $platform,
        private readonly InvestmentPostings $investments,
        private readonly CyclePostings $cycles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->postedSources = JournalEntry::query()
            ->whereNotNull('source_type')
            ->get(['source_type', 'source_id'])
            ->mapWithKeys(fn (JournalEntry $entry): array => [$entry->source_type.':'.$entry->source_id => true])
            ->all();

        DB::beginTransaction();

        try {
            $counts = $this->replay();
            $legacy = $this->closeFinalizedEvents();
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('Backfill stopped. Nothing was saved.');
            $this->line($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->implode(' ')
                : $exception->getMessage());

            return self::FAILURE;
        }

        $this->report($counts, $legacy);

        $checkPassed = Artisan::call('ledger:check') === self::SUCCESS;
        $this->newLine();
        $this->line(trim(Artisan::output()));

        if ($dryRun || ! $checkPassed) {
            DB::rollBack();
            $this->newLine();
            $dryRun
                ? $this->warn('Dry run: everything rolled back. Run without --dry-run to save.')
                : $this->error('ledger:check failed: everything rolled back.');

            return $checkPassed ? self::SUCCESS : self::FAILURE;
        }

        DB::commit();
        $this->newLine();
        $this->info('Backfill saved.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int> posted count per document type
     */
    private function replay(): array
    {
        $counts = [];

        $this->timeline()
            ->sortBy([['date', 'asc'], ['priority', 'asc'], ['created', 'asc'], ['id', 'asc']])
            ->each(function (array $item) use (&$counts): void {
                try {
                    ($item['post'])();
                } catch (\Throwable $exception) {
                    throw new \RuntimeException(sprintf(
                        '%s (%s, dated %s): %s',
                        $item['label'],
                        $item['type'],
                        $item['date'],
                        $exception instanceof ValidationException
                            ? collect($exception->errors())->flatten()->implode(' ')
                            : $exception->getMessage(),
                    ), previous: $exception);
                }

                $counts[$item['type']] = ($counts[$item['type']] ?? 0) + 1;
            });

        return $counts;
    }

    /**
     * Every unposted money document. On the same day, money coming in is
     * replayed before money going out.
     *
     * @return Collection<int, array{type: string, label: string, date: string, priority: int, created: int, id: int, post: callable}>
     */
    private function timeline(): Collection
    {
        $items = collect();

        $add = function (string $type, Model $source, mixed $date, int $priority, callable $post) use ($items): void {
            if (isset($this->postedSources[$source->getMorphClass().':'.$source->getKey()])) {
                return;
            }

            $items->push([
                'type' => $type,
                'label' => class_basename($source).' #'.$source->getKey(),
                'date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) ($date ?? now()->toDateString()),
                'priority' => $priority,
                'created' => $source->created_at?->getTimestamp() ?? 0,
                'id' => (int) $source->getKey(),
                'post' => $post,
            ]);
        };

        DepositSubmission::query()->where('status', DepositSubmissionStatus::Verified)->get()
            ->each(fn (DepositSubmission $deposit) => $add('Member deposits', $deposit, $deposit->deposit_date, 0,
                fn () => $this->members->depositVerified($deposit, $this->user($deposit->verified_by_user_id))));

        GeneralIncome::query()->get()
            ->each(fn (GeneralIncome $income) => $add('General incomes', $income, $income->income_date, 1,
                fn () => $this->platform->incomeRecorded($income, $this->user($income->created_by_user_id))));

        ChargeAllocation::query()->with(['charge.member', 'charge.category'])->get()
            ->each(fn (ChargeAllocation $allocation) => $add('Charge settlements', $allocation, $allocation->confirmed_at, 2, function () use ($allocation): void {
                $this->members->chargeSettled($allocation);

                if ($allocation->reversed_at !== null) {
                    $this->members->chargeAllocationReversed($allocation, $this->user($allocation->reversed_by_user_id));
                }
            }));

        FundCycleAllocation::query()->with(['member', 'fundCycle'])->get()
            ->each(fn (FundCycleAllocation $allocation) => $add('Cycle allocations', $allocation, $allocation->allocated_at, 3,
                fn () => $this->members->cycleAllocated($allocation, $this->user($allocation->created_by_user_id))));

        EventPayment::query()->where('payment_status', 'verified')->whereHas('order')->with('order')->get()
            ->each(fn (EventPayment $payment) => $add('Customer payments', $payment, $payment->verified_at, 4,
                fn () => $this->investments->customerPaymentVerified($payment, $this->user($payment->verified_by_user_id))));

        EventIncome::query()->get()
            ->each(fn (EventIncome $income) => $add('Event other incomes', $income, $income->income_date, 5,
                fn () => $this->investments->eventIncome($income, $this->user($income->created_by_user_id))));

        FundCycleTransaction::query()->with('fundCycle')->get()
            ->each(fn (FundCycleTransaction $transaction) => $add('Cycle transactions', $transaction, $transaction->transaction_date, $transaction->direction === 'income' ? 5 : 8,
                fn () => $this->cycles->transaction($transaction, $this->user($transaction->created_by_user_id))));

        BusinessTransaction::query()->with('investment')->get()
            ->each(fn (BusinessTransaction $transaction) => $add('Business transactions', $transaction, $transaction->transaction_date, in_array($transaction->type, ['invest', 'expense', 'capital_loss'], true) ? 7 : 5,
                fn () => $this->investments->businessTransaction($transaction, $this->user($transaction->created_by_user_id))));

        EventBankDeposit::query()->get()
            ->each(fn (EventBankDeposit $deposit) => $add('Event bank deposits', $deposit, $deposit->deposit_date, 6,
                fn () => $this->investments->eventBankDeposit($deposit, $this->user($deposit->created_by_user_id))));

        EventBankWithdrawal::query()->get()
            ->each(fn (EventBankWithdrawal $withdrawal) => $add('Event bank withdrawals', $withdrawal, $withdrawal->withdrawal_date, 7,
                fn () => $this->investments->eventWithdrawal($withdrawal, $this->user($withdrawal->created_by_user_id))));

        EventExpense::query()->get()
            ->each(fn (EventExpense $expense) => $add('Event expenses', $expense, $expense->expense_date, 8,
                fn () => $this->investments->eventExpense($expense, $this->user($expense->created_by_user_id))));

        CycleInvestmentCharge::query()->with('investment')->get()
            ->each(fn (CycleInvestmentCharge $charge) => $add('Platform charges', $charge, $charge->charged_at, 8,
                fn () => $this->investments->charge($charge, $this->user($charge->created_by_user_id))));

        GeneralExpense::query()->get()
            ->each(fn (GeneralExpense $expense) => $add('General expenses', $expense, $expense->expense_date, 9,
                fn () => $this->platform->expenseRecorded($expense, $this->user($expense->created_by_user_id))));

        EventRefund::query()->whereHas('order')->with('order')->get()
            ->each(fn (EventRefund $refund) => $add('Customer refunds', $refund, $refund->refunded_at, 9,
                fn () => $this->investments->refund($refund, $this->user($refund->created_by_user_id))));

        PayoutRequest::query()->where('status', PayoutRequest::STATUS_PAID)->get()
            ->each(fn (PayoutRequest $payout) => $add('Member payouts', $payout, $payout->processed_at, 10,
                fn () => $this->members->payoutPaid($payout, $this->user($payout->processed_by_user_id))));

        return $items;
    }

    /**
     * Events finalized before the journal existed must be closed into the
     * cycle result. The old system kept no cash/bKash split and treated
     * `bank deposits − withdrawals` as the event result, with any gap shown as
     * "other income". Mirror that, then close.
     *
     * @return list<array{event: string, bkash_moved: int, other_income: int, status: string}>
     */
    private function closeFinalizedEvents(): array
    {
        $rows = [];

        FundCycleEvent::query()->where('is_finalized', true)->orderBy('id')->get()
            ->each(function (FundCycleEvent $event) use (&$rows): void {
                $investment = $event->ensureInvestment();

                if ($investment->isClosed()) {
                    return;
                }

                $row = ['event' => "#{$event->id} {$event->title}", 'bkash_moved' => 0, 'other_income' => 0, 'status' => 'closed'];
                $dims = $investment->dimensions();

                if (($bkash = $this->investments->bkash($investment)) !== 0) {
                    $this->ledger->entry('legacy_bkash_to_cash', "Legacy: bKash balance moved to event cash for {$investment->title}")
                        ->source($investment)
                        ->key('legacy-bkash-to-cash:'.$investment->id)
                        ->signed(Account::EventCash, $bkash, $dims, 'pre-journal event: no cash/bKash split')
                        ->signed(Account::Bkash, -$bkash, $dims, 'pre-journal event: no cash/bKash split')
                        ->post();

                    $row['bkash_moved'] = $bkash;
                }

                $cash = $this->investments->cash($investment);

                if ($cash < 0) {
                    $income = EventIncome::query()->create([
                        'fund_cycle_event_id' => $event->id,
                        'income_date' => $event->bankDeposits()->max('deposit_date') ?? now()->toDateString(),
                        'category' => 'other',
                        'received_via' => 'cash',
                        'amount' => Money::toTaka(-$cash),
                        'description' => 'Legacy reconciliation: bank deposits exceeded recorded sales minus expenses (shown as "other income" before the journal).',
                    ]);

                    $this->investments->eventIncome($income);
                    $row['other_income'] = -$cash;
                }

                $blockers = $this->investments->closeBlockers($investment);

                if ($blockers === []) {
                    $this->investments->close($investment);
                } else {
                    $row['status'] = 'NOT closed: '.implode(' ', $blockers);
                }

                $rows[] = $row;
            });

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<array{event: string, bkash_moved: int, other_income: int, status: string}>  $legacy
     */
    private function report(array $counts, array $legacy): void
    {
        $this->info('Posted documents');
        $this->table(
            ['Document', 'Entries posted'],
            collect($counts)->map(fn (int $count, string $type): array => [$type, $count])->values()->all() ?: [['(none)', 0]],
        );

        if ($legacy !== []) {
            $this->info('Finalized events closed');
            $this->table(
                ['Event', 'bKash → cash', 'Legacy other income', 'Status'],
                array_map(fn (array $row): array => [
                    $row['event'],
                    Money::format($row['bkash_moved']),
                    Money::format($row['other_income']),
                    $row['status'],
                ], $legacy),
            );
        }

        $this->info('Balances after backfill (BDT)');
        $this->table(['Figure', 'Amount'], [
            ['Joint bank (compare with the bank statement)', Money::format($this->ledger->balance(Account::Bank))],
            ['bKash wallet', Money::format($this->ledger->balance(Account::Bkash))],
            ['Event cash', Money::format($this->ledger->balance(Account::EventCash))],
            ['Member available (2010)', Money::format($this->ledger->creditBalance(Account::MemberBalance))],
            ['Member cycle capital (2020)', Money::format($this->ledger->creditBalance(Account::CycleCapital))],
            ['Undistributed cycle results (2030)', Money::format($this->ledger->creditBalance(Account::CycleResult))],
            ['Platform fund', Money::format($this->ledger->platformFund())],
        ]);

        $this->table(
            ['Fund cycle', 'Status', 'Capital', 'Cycle bank', 'Result so far'],
            FundCycle::query()->orderBy('id')->get()->map(function (FundCycle $cycle): array {
                $summary = $this->cycles->summary($cycle);

                return [$cycle->name, $cycle->status, Money::format($summary['capital']), Money::format($summary['cash']), Money::format($summary['result'])];
            })->all(),
        );
    }

    private function user(mixed $id): ?User
    {
        if (! $id) {
            return null;
        }

        return $this->users[(int) $id] ??= User::query()->find($id);
    }
}
