<?php

namespace App\Console\Commands;

use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Models\FundCycle;
use App\Models\JournalLine;
use Illuminate\Console\Command;

/**
 * Integrity checks over the journal. Exit code 1 when anything is off.
 */
class LedgerCheck extends Command
{
    protected $signature = 'ledger:check';

    protected $description = 'Verify the double-entry journal: balanced entries, no negative balances';

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
}
