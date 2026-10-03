<?php

namespace App\Models;

use App\Ledger\Exceptions\ImmutableJournal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'journal_entry_id',
    'ledger_account_id',
    'debit',
    'credit',
    'user_id',
    'member_id',
    'fund_cycle_id',
    'cycle_investment_id',
    'event_order_id',
    'memo',
])]
class JournalLine extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableJournal('Journal lines cannot be changed.'));
        static::deleting(fn () => throw new ImmutableJournal('Journal lines cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fundCycle(): BelongsTo
    {
        return $this->belongsTo(FundCycle::class);
    }

    public function cycleInvestment(): BelongsTo
    {
        return $this->belongsTo(CycleInvestment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EventOrder::class, 'event_order_id');
    }
}
