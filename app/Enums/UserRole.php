<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    public function isAdmin(): bool
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN => true,
            self::MEMBER => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::MEMBER => 'Member',
        };
    }

    public static function values(): array
    {
        return array_map(
            static fn(self $role) => $role->value,
            self::cases(),
        );
    }
}
