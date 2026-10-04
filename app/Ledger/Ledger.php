<?php

namespace App\Ledger;

use App\Ledger\Exceptions\UnbalancedEntry;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Double-entry journal. Every money movement is posted here; every balance is
 * read from here. Entries are immutable — corrections are reversals.
 */
class Ledger
{
    public const DIMENSIONS = ['user_id', 'member_id', 'fund_cycle_id', 'cycle_investment_id', 'event_order_id'];

    public function entry(string $kind, string $description): EntryBuilder
    {
        return new EntryBuilder($this, $kind, $description);
    }

    /**
     * @param  list<array{account: Account, debit: int, credit: int, dims: array<string, int|null>, memo: ?string}>  $lines
     */
    public function persist(
        string $kind,
        string $description,
        string $entryDate,
        ?Model $source,
        string $idempotencyKey,
        ?User $postedBy,
        array $lines,
        ?int $reversalOfId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): JournalEntry {
        $lines = array_values(array_filter($lines, fn (array $line): bool => $line['debit'] > 0 || $line['credit'] > 0));

        if (count($lines) < 2) {
            throw new UnbalancedEntry("Journal entry [{$kind}] needs at least two lines.");
        }

        $debits = array_sum(array_column($lines, 'debit'));
        $credits = array_sum(array_column($lines, 'credit'));

        if ($debits !== $credits) {
            throw new UnbalancedEntry("Journal entry [{$kind}] is unbalanced: debit {$debits} ≠ credit {$credits}.");
        }

        return DB::transaction(function () use (
            $kind, $description, $entryDate, $source, $idempotencyKey, $postedBy, $lines, $reversalOfId, $sourceType, $sourceId,
        ): JournalEntry {
            $existing = JournalEntry::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }

            $entry = JournalEntry::query()->create([
                'entry_date' => $entryDate,
                'kind' => $kind,
                'description' => mb_substr($description, 0, 255),
                'source_type' => $sourceType ?? $source?->getMorphClass(),
                'source_id' => $sourceId ?? $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'reversal_of_id' => $reversalOfId,
                'posted_by_user_id' => $postedBy?->id,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => LedgerAccount::idFor($line['account']),
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    ...array_intersect_key($line['dims'], array_flip(self::DIMENSIONS)),
                    'memo' => $line['memo'],
                ]);
            }

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, string $description, ?User $by = null): JournalEntry
    {
        if ($entry->reversal_of_id !== null) {
            throw new UnbalancedEntry('A reversal entry cannot itself be reversed.');
        }

        $existing = JournalEntry::query()->where('reversal_of_id', $entry->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = $entry->lines()->get()->map(fn (JournalLine $line): array => [
            'account' => LedgerAccount::query()->findOrFail($line->ledger_account_id)->account(),
            'debit' => $line->credit,
            'credit' => $line->debit,
            'dims' => $line->only(self::DIMENSIONS),
            'memo' => $line->memo,
        ])->all();

        return $this->persist(
            kind: $entry->kind.'.reversal',
            description: $description,
            entryDate: now()->toDateString(),
            source: null,
            idempotencyKey: 'reversal:'.$entry->id,
            postedBy: $by,
            lines: $lines,
            reversalOfId: $entry->id,
            sourceType: $entry->source_type,
            sourceId: $entry->source_id,
        );
    }

    /**
     * Reverse every still-active entry posted for a source document.
     */
    public function reverseSource(Model $source, string $description, ?User $by = null): void
    {
        $this->activeEntriesFor($source)->each(
            fn (JournalEntry $entry) => $this->reverse($entry, $description, $by),
        );
    }

    /**
     * @return Collection<int, JournalEntry>
     */
    public function activeEntriesFor(Model $source): Collection
    {
        return JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Next free idempotency key for a source that may be re-posted after edits.
     */
    public function versionedKey(string $prefix, Model $source): string
    {
        $count = JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')
            ->where('idempotency_key', 'like', $prefix.':%')
            ->count();

        return $prefix.':v'.($count + 1);
    }

    /**
     * Debit − credit, in paisa. Positive for debit-normal accounts in surplus.
     *
     * @param  Account|list<Account>  $accounts
     * @param  array<string, int|null>  $dims  key present with null value means "IS NULL"
     */
    public function balance(Account|array $accounts, array $dims = []): int
    {
        $row = $this->linesQuery($accounts, $dims)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->first();

        return (int) ($row->balance ?? 0);
    }

    /**
     * Credit − debit, in paisa. Positive for liabilities, equity and income.
     *
     * @param  Account|list<Account>  $accounts
     * @param  array<string, int|null>  $dims
     */
    public function creditBalance(Account|array $accounts, array $dims = []): int
    {
        return -$this->balance($accounts, $dims);
    }

    /**
     * @param  Account|list<Account>  $accounts
     * @param  array<string, int|null>  $dims
     * @return Collection<int|string, int> debit − credit keyed by the group column value
     */
    public function balancesBy(string $groupColumn, Account|array $accounts, array $dims = []): Collection
    {
        return $this->linesQuery($accounts, $dims)
            ->selectRaw("{$groupColumn} as group_key")
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->groupBy($groupColumn)
            ->pluck('balance', 'group_key')
            ->map(fn ($balance): int => (int) $balance);
    }

    /**
     * Debit − credit per entry kind, with reversals folded into the kind they
     * reverse (so a reversed fee nets to zero under `fee_settled`).
     *
     * @param  Account|list<Account>  $accounts
     * @param  array<string, int|null>  $dims
     * @return Collection<string, int>
     */
    public function movementsByKind(Account|array $accounts, array $dims = []): Collection
    {
        return $this->linesQuery($accounts, $dims)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->selectRaw('journal_entries.kind as kind')
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->groupBy('journal_entries.kind')
            ->pluck('balance', 'kind')
            ->reduce(function (Collection $carry, $balance, string $kind): Collection {
                $base = str($kind)->beforeLast('.reversal')->toString();

                return $carry->put($base, $carry->get($base, 0) + (int) $balance);
            }, collect());
    }

    /**
     * @return Collection<string, int> debit − credit keyed by account code
     */
    public function balancesByAccount(array $dims = []): Collection
    {
        return JournalLine::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->tap(fn (Builder $query) => $this->applyDimensions($query, $dims))
            ->selectRaw('ledger_accounts.code as code')
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->groupBy('ledger_accounts.code')
            ->pluck('balance', 'code')
            ->map(fn ($balance): int => (int) $balance);
    }

    /**
     * Serialize concurrent postings that check-then-spend the same balance.
     * Must be called inside a DB transaction.
     */
    public function lock(string ...$keys): void
    {
        sort($keys);

        foreach (array_unique($keys) as $key) {
            DB::table('ledger_locks')->insertOrIgnore(['key' => $key]);
            DB::table('ledger_locks')->where('key', $key)->lockForUpdate()->first();
        }
    }

    /**
     * Lock, then assert the balance stays non-negative after spending.
     *
     * @param  array<string, int|null>  $dims
     */
    public function assertAvailable(
        Account $account,
        array $dims,
        int $amount,
        string $field,
        string $message,
    ): void {
        $available = $account->isDebitNormal()
            ? $this->balance($account, $dims)
            : $this->creditBalance($account, $dims);

        if ($amount > $available) {
            throw ValidationException::withMessages([
                $field => sprintf('%s (available: %s BDT)', $message, number_format($available / 100, 2)),
            ]);
        }
    }

    public function platformFund(): int
    {
        return $this->creditBalance(Account::platformFund());
    }

    /**
     * @param  Account|list<Account>  $accounts
     * @param  array<string, int|null>  $dims
     */
    private function linesQuery(Account|array $accounts, array $dims): Builder
    {
        $accounts = is_array($accounts) ? $accounts : [$accounts];

        $query = JournalLine::query()->whereIn('ledger_account_id', LedgerAccount::idsFor($accounts));

        $this->applyDimensions($query, $dims);

        return $query;
    }

    /**
     * @param  array<string, int|null>  $dims
     */
    private function applyDimensions(Builder $query, array $dims): void
    {
        foreach ($dims as $column => $value) {
            if (! in_array($column, self::DIMENSIONS, true)) {
                continue;
            }

            $value === null
                ? $query->whereNull("journal_lines.{$column}")
                : $query->where("journal_lines.{$column}", $value);
        }
    }
}
