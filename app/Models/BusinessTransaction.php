<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cycle_investment_id',
    'type',
    'amount',
    'transaction_date',
    'description',
    'reference_no',
    'receipt_path',
    'created_by_user_id',
])]
class BusinessTransaction extends Model
{
    use SoftDeletes;

    public const TYPES = ['invest', 'profit', 'capital_return', 'capital_loss', 'other_income', 'expense'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'invest' => 'Capital invested',
            'profit' => 'Profit received',
            'capital_return' => 'Capital returned',
            'capital_loss' => 'Capital loss / write-off',
            'other_income' => 'Other income',
            default => 'Expense',
        };
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(CycleInvestment::class, 'cycle_investment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
