<?php

namespace App\Enums;

enum EventPackageUnitType: string
{
    case Gram = 'gram';

    case Kg = 'kg';

    case Ml = 'ml';

    case Liter = 'liter';

    case Piece = 'piece';

    case Pack = 'pack';

    case Box = 'box';

    case Dozen = 'dozen';

    case Set = 'set';

    case Bundle = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'Gram',
            self::Kg => 'KG',
            self::Ml => 'ML',
            self::Liter => 'Liter',
            self::Piece => 'Piece',
            self::Pack => 'Pack',
            self::Box => 'Box',
            self::Dozen => 'Dozen',
            self::Set => 'Set',
            self::Bundle => 'Bundle',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Gram => 'g',
            self::Kg => 'kg',
            self::Ml => 'ml',
            self::Liter => 'L',
            self::Piece => 'pc',
            self::Pack => 'pack',
            self::Box => 'box',
            self::Dozen => 'dz',
            self::Set => 'set',
            self::Bundle => 'bundle',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }

    public function formatSize(float|string $size): string
    {
        $amount = self::formatAmount($size);

        return "{$amount} {$this->shortLabel()}";
    }

    public static function formatAmount(float|string $size): string
    {
        $numeric = (float) $size;

        if (fmod($numeric, 1.0) === 0.0) {
            return (string) (int) $numeric;
        }

        return rtrim(rtrim(number_format($numeric, 3, '.', ''), '0'), '.');
    }

    public static function formatPhysicalQuantity(
        float|string $unitSize,
        self|string $unitType,
        int $packQuantity,
    ): string {
        $type = is_string($unitType) ? self::from($unitType) : $unitType;
        $physical = (float) $unitSize * $packQuantity;

        return $type->formatSize($physical);
    }

    public static function formatPackLine(
        float|string $unitSize,
        self|string $unitType,
        int $packQuantity,
    ): string {
        $type = is_string($unitType) ? self::from($unitType) : $unitType;
        $perPack = $type->formatSize($unitSize);

        if ($packQuantity === 1) {
            return "1 × {$perPack}";
        }

        $physical = self::formatPhysicalQuantity($unitSize, $type, $packQuantity);

        return "{$packQuantity} × {$perPack} = {$physical}";
    }

    /**
     * @param  iterable<array{unit_type: self|string, unit_size: float|string, quantity: int}>  $items
     * @return array<string, float>
     */
    public static function totalsByUnitType(iterable $items): array
    {
        $totals = [];

        foreach ($items as $item) {
            $type = $item['unit_type'] instanceof self
                ? $item['unit_type']->value
                : (string) $item['unit_type'];
            $physical = (float) $item['unit_size'] * (int) $item['quantity'];
            $totals[$type] = ($totals[$type] ?? 0) + $physical;
        }

        return $totals;
    }

    /**
     * @param  array<string, float>  $totalsByType
     */
    public static function formatTotalsSummary(array $totalsByType): string
    {
        if ($totalsByType === []) {
            return '';
        }

        $parts = [];

        foreach ($totalsByType as $typeValue => $amount) {
            $type = self::from($typeValue);
            $parts[] = $type->formatSize($amount);
        }

        return implode(', ', $parts);
    }
}
