<?php

namespace App\Models;

use App\Ledger\Exceptions\ImmutableJournal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'entry_date',
    'kind',
    'description',
    'source_type',
    'source_id',
    'idempotency_key',
    'reversal_of_id',
    'posted_by_user_id',
    'posted_at',
])]
class JournalEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableJournal('Journal entries cannot be changed. Post a reversal instead.'));
        static::deleting(fn () => throw new ImmutableJournal('Journal entries cannot be deleted. Post a reversal instead.'));
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
