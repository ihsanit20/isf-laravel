<?php

namespace App\Ledger;

/**
 * Chart of accounts. The backing value is the account code stored in
 * `ledger_accounts.code`. See plans/journal-ledger-plan.md §2.
 */
enum Account: string
{
    // Assets
    case Bank = '1010';
    case Bkash = '1020';
    case EventCash = '1030';
    case BusinessInvestment = '1210';

    // Liabilities: money owed to users (contacts)
    case MemberBalance = '2010';
    case CycleCapital = '2020';
    case CycleResult = '2030';

    // Equity: platform
    case PlatformEquity = '3010';
    case OpeningBalance = '3900';

    // Fund (cycle) income
    case EventSales = '4110';
    case EventSalesRefund = '4120';
    case SubBusinessOtherIncome = '4150';
    case BusinessProfit = '4310';
    case CycleIncome = '4510';

    // Fund (cycle) expense
    case SubBusinessExpense = '5110';
    case GatewayFee = '5310';
    case BusinessCapitalLoss = '5410';
    case PlatformServiceCharge = '5510';
    case AssetRent = '5520';
    case OtherPlatformCharge = '5590';
    case CycleExpense = '5610';

    // Platform income
    case RegistrationFeeIncome = '6010';
    case OtherFeeIncome = '6020';
    case PlatformServiceIncome = '6030';
    case AssetRentIncome = '6040';
    case OtherChargeIncome = '6050';
    case PlatformOtherIncome = '6090';

    // Platform expense
    case BankChargeExpense = '7010';
    case SmsChargeExpense = '7020';
    case ItExpense = '7030';
    case OfficeExpense = '7040';
    case PlatformOtherExpense = '7090';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank (joint)',
            self::Bkash => 'bKash merchant wallet',
            self::EventCash => 'Event cash / float',
            self::BusinessInvestment => 'Business investment',
            self::MemberBalance => 'Member balance',
            self::CycleCapital => 'Member cycle capital',
            self::CycleResult => 'Cycle result (undistributed)',
            self::PlatformEquity => 'Platform equity',
            self::OpeningBalance => 'Opening balance adjustment',
            self::EventSales => 'Event sales',
            self::EventSalesRefund => 'Event sales refund',
            self::SubBusinessOtherIncome => 'Sub-business other income',
            self::BusinessProfit => 'Business profit received',
            self::CycleIncome => 'Cycle-level income',
            self::SubBusinessExpense => 'Sub-business expense',
            self::GatewayFee => 'Payment gateway fee',
            self::BusinessCapitalLoss => 'Business capital loss',
            self::PlatformServiceCharge => 'Platform service charge',
            self::AssetRent => 'Asset rent',
            self::OtherPlatformCharge => 'Other platform charge',
            self::CycleExpense => 'Cycle-level expense',
            self::RegistrationFeeIncome => 'Registration / membership fee',
            self::OtherFeeIncome => 'Other fee',
            self::PlatformServiceIncome => 'Platform service charge income',
            self::AssetRentIncome => 'Asset rent income',
            self::OtherChargeIncome => 'Other platform charge income',
            self::PlatformOtherIncome => 'Other platform income',
            self::BankChargeExpense => 'Bank charge',
            self::SmsChargeExpense => 'SMS charge',
            self::ItExpense => 'IT / hosting',
            self::OfficeExpense => 'Office / printing / transport',
            self::PlatformOtherExpense => 'Other platform expense',
        };
    }

    /**
     * @return 'asset'|'liability'|'equity'|'income'|'expense'
     */
    public function type(): string
    {
        return match ($this->value[0]) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4', '6' => 'income',
            default => 'expense',
        };
    }

    /**
     * Income/expense scope: cycle (fund) accounts close into CycleResult,
     * platform accounts belong to the platform alone.
     *
     * @return 'fund'|'platform'|null
     */
    public function scope(): ?string
    {
        return match ($this->value[0]) {
            '4', '5' => 'fund',
            '6', '7' => 'platform',
            default => null,
        };
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type(), ['asset', 'expense'], true);
    }

    /**
     * @return list<self>
     */
    public static function fundProfitAndLoss(): array
    {
        return array_values(array_filter(self::cases(), fn (self $account) => $account->scope() === 'fund'));
    }

    /**
     * Accounts whose credit balance is the platform's own fund.
     *
     * @return list<self>
     */
    public static function platformFund(): array
    {
        return [
            self::PlatformEquity,
            ...array_values(array_filter(self::cases(), fn (self $account) => $account->scope() === 'platform')),
        ];
    }
}
