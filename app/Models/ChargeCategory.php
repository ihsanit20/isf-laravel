<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'title',
    'default_amount',
    'is_active',
])]
class ChargeCategory extends Model
{
    public const CODE_REGISTRATION_FEE = 'registration_fee';

    protected function casts(): array
    {
        return [
            'default_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
