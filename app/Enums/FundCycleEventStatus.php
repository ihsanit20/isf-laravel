<?php

namespace App\Enums;

enum FundCycleEventStatus: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::editable(),
        );
    }

    /**
     * Statuses an admin can pick in the event form. Cancelling is its own
     * action because it needs checks and locks the event.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return [self::Draft, self::Published];
    }

    /**
     * @return list<string>
     */
    public static function editableValues(): array
    {
        return array_column(self::editable(), 'value');
    }
}
