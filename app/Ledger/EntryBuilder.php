<?php

namespace App\Ledger;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EntryBuilder
{
    private string $entryDate;

    private ?Model $source = null;

    private ?string $key = null;

    private ?User $postedBy = null;

    /** @var list<array{account: Account, debit: int, credit: int, dims: array<string, int|null>, memo: ?string}> */
    private array $lines = [];

    public function __construct(
        private readonly Ledger $ledger,
        private readonly string $kind,
        private readonly string $description,
    ) {
        $this->entryDate = now()->toDateString();
    }

    public function on(mixed $date): self
    {
        $this->entryDate = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) ($date ?? now()->toDateString());

        return $this;
    }

    public function source(Model $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function by(?User $user): self
    {
        $this->postedBy = $user;

        return $this;
    }

    /**
     * @param  array<string, int|null>  $dims
     */
    public function debit(Account $account, int $amount, array $dims = [], ?string $memo = null): self
    {
        return $this->line($account, $amount, 0, $dims, $memo);
    }

    /**
     * @param  array<string, int|null>  $dims
     */
    public function credit(Account $account, int $amount, array $dims = [], ?string $memo = null): self
    {
        return $this->line($account, 0, $amount, $dims, $memo);
    }

    /**
     * Signed helper: positive amount debits, negative amount credits.
     *
     * @param  array<string, int|null>  $dims
     */
    public function signed(Account $account, int $amount, array $dims = [], ?string $memo = null): self
    {
        return $amount >= 0
            ? $this->debit($account, $amount, $dims, $memo)
            : $this->credit($account, -$amount, $dims, $memo);
    }

    public function hasLines(): bool
    {
        return array_filter($this->lines, fn (array $line): bool => $line['debit'] > 0 || $line['credit'] > 0) !== [];
    }

    public function post(): JournalEntry
    {
        if ($this->key === null) {
            throw new LogicException("Journal entry [{$this->kind}] needs an idempotency key.");
        }

        return $this->ledger->persist(
            kind: $this->kind,
            description: $this->description,
            entryDate: $this->entryDate,
            source: $this->source,
            idempotencyKey: $this->key,
            postedBy: $this->postedBy,
            lines: $this->lines,
        );
    }

    /**
     * @param  array<string, int|null>  $dims
     */
    private function line(Account $account, int $debit, int $credit, array $dims, ?string $memo): self
    {
        if ($debit < 0 || $credit < 0) {
            throw new LogicException('Journal line amounts must be non-negative.');
        }

        $this->lines[] = [
            'account' => $account,
            'debit' => $debit,
            'credit' => $credit,
            'dims' => $dims,
            'memo' => $memo,
        ];

        return $this;
    }
}
