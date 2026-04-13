<?php

namespace App\Enums;

enum GeneralExpenseCategory: string
{
    case OfficeSupplies = 'office_supplies';

    case Printing = 'printing';

    case BankCharge = 'bank_charge';

    case ItExpense = 'it_expense';

    case Transport = 'transport';

    case Refreshment = 'refreshment';

    case Utility = 'utility';

    case ServiceFee = 'service_fee';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OfficeSupplies => 'Office Supplies',
            self::Printing => 'Printing',
            self::BankCharge => 'Bank Charge',
            self::ItExpense => 'IT Expense',
            self::Transport => 'Transport',
            self::Refreshment => 'Refreshment',
            self::Utility => 'Utility',
            self::ServiceFee => 'Service Fee',
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
