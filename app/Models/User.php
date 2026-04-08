<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_HIERARCHY = [
        'member' => 1,
        'admin' => 2,
        'super_admin' => 3,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public static function roles(): array
    {
        return array_keys(self::ROLE_HIERARCHY);
    }

    public static function roleRank(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 0;
    }

    public static function assignableRolesFor(string $role): array
    {
        $maxRank = self::roleRank($role);

        return array_values(array_filter(
            self::roles(),
            fn (string $candidate): bool => self::roleRank($candidate) <= $maxRank,
        ));
    }

    public function canAssignRole(string $role): bool
    {
        return self::roleRank($role) <= self::roleRank($this->role);
    }

    public function canManageUser(self $user): bool
    {
        return self::roleRank($user->role) <= self::roleRank($this->role);
    }
}
