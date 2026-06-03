<?php

use App\Enums\EventPackageUnitType;

test('formatSize renders whole and fractional amounts', function () {
    expect(EventPackageUnitType::Kg->formatSize(1))->toBe('1 kg')
        ->and(EventPackageUnitType::Gram->formatSize(500))->toBe('500 g')
        ->and(EventPackageUnitType::Liter->formatSize(2.5))->toBe('2.5 L');
});

test('formatPackLine shows pack multiplication and total', function () {
    expect(EventPackageUnitType::formatPackLine(1, EventPackageUnitType::Kg, 3))
        ->toBe('3 × 1 kg = 3 kg');
});

test('formatTotalsSummary joins totals by unit type', function () {
    $summary = EventPackageUnitType::formatTotalsSummary([
        'kg' => 3,
        'liter' => 2,
    ]);

    expect($summary)->toBe('3 kg, 2 L');
});
