<?php

namespace App\Models;

use App\Enums\GeneralExpenseCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'expense_date',
    'category',
    'amount',
    'description',
    'receipt_path',
    'created_by_user_id',
])]
class GeneralExpense extends Model
{
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'category' => GeneralExpenseCategory::class,
            'amount' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function attachmentDisk(): string
    {
        return (string) config('filesystems.general_expense_attachments_disk', 'public');
    }

    public function receiptUrl(): ?string
    {
        if ($this->receipt_path === null) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(self::attachmentDisk());

        return $disk->url($this->receipt_path);
    }
}
