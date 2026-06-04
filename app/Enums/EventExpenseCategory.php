<?php

namespace App\Enums;

enum EventExpenseCategory: string
{
    case Procurement = 'procurement';

    case Packaging = 'packaging';

    case Transport = 'transport';

    case Labor = 'labor';

    case Marketing = 'marketing';

    case HubOps = 'hub_ops';

    case PaymentFee = 'payment_fee';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Procurement => 'Procurement',
            self::Packaging => 'Packaging',
            self::Transport => 'Transport',
            self::Labor => 'Labor',
            self::Marketing => 'Marketing',
            self::HubOps => 'Hub / Pickup Ops',
            self::PaymentFee => 'Payment Fee',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases(),
        );
    }
}
