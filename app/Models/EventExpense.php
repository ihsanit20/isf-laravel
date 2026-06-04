<?php

namespace App\Models;

use App\Enums\EventExpenseCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'fund_cycle_event_id',
    'expense_date',
    'category',
    'amount',
    'description',
    'receipt_path',
    'created_by_user_id',
])]
class EventExpense extends Model
{
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'category' => EventExpenseCategory::class,
            'amount' => 'integer',
        ];
    }

    public function fundCycleEvent(): BelongsTo
    {
        return $this->belongsTo(FundCycleEvent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function attachmentDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    public function receiptUrl(): ?string
    {
        if ($this->receipt_path === null) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::attachmentDisk());

        return $disk->url($this->receipt_path);
    }
}
