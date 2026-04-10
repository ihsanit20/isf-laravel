<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'managed_by_user_id',
    'full_name',
    'phone',
    'relationship_to_user',
    'units',
    'status',
    'applied_at',
    'approved_at',
    'approved_by_user_id',
    'rejection_note',
])]
class Member extends Model
{
    public const RELATIONSHIP_SELF = 'self';

    public const RELATIONSHIP_SPOUSE = 'spouse';

    public const RELATIONSHIP_CHILD = 'child';

    public const RELATIONSHIP_PARENT = 'parent';

    public const RELATIONSHIP_OTHER = 'other';

    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'units' => 'integer',
        ];
    }

    public static function relationshipOptions(): array
    {
        return [
            self::RELATIONSHIP_SELF,
            self::RELATIONSHIP_SPOUSE,
            self::RELATIONSHIP_CHILD,
            self::RELATIONSHIP_PARENT,
            self::RELATIONSHIP_OTHER,
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function depositAllocations(): HasMany
    {
        return $this->hasMany(DepositAllocation::class);
    }

    public function charges(): MorphMany
    {
        return $this->morphMany(Charge::class, 'chargeable');
    }
}
