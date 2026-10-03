<?php

namespace App\Models;

use App\Ledger\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'scope', 'is_active'])]
class LedgerAccount extends Model
{
    /** @var array<string, int> */
    private static array $idsByCode = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function idFor(Account $account): int
    {
        if (! isset(self::$idsByCode[$account->value])) {
            self::$idsByCode[$account->value] = (int) self::query()->firstOrCreate(
                ['code' => $account->value],
                [
                    'name' => $account->label(),
                    'type' => $account->type(),
                    'scope' => $account->scope(),
                ],
            )->id;
        }

        return self::$idsByCode[$account->value];
    }

    /**
     * @param  list<Account>  $accounts
     * @return list<int>
     */
    public static function idsFor(array $accounts): array
    {
        return array_map(fn (Account $account): int => self::idFor($account), $accounts);
    }

    public function account(): ?Account
    {
        return Account::tryFrom($this->code);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
