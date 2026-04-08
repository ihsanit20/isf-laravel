<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Exited = 'exited';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
