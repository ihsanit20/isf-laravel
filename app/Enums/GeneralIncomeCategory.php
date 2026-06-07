<?php

namespace App\Enums;

enum GeneralIncomeCategory: string
{
    case PlatformFee = 'platform_fee';

    case Sponsorship = 'sponsorship';

    case SaleProceeds = 'sale_proceeds';

    case RentalIncome = 'rental_income';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PlatformFee => 'Platform Fee',
            self::Sponsorship => 'Sponsorship',
            self::SaleProceeds => 'Sale Proceeds',
            self::RentalIncome => 'Rental Income',
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
            fn(self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases(),
        );
    }
}
