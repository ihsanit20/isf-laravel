<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

trait BuildsMemberUnitCalendar
{
    protected function buildMemberUnitCalendar(Member $member, ?int $requestedYear = null): array
    {
        $member->load([
            'depositAllocations',
            'manager:id,name,email',
        ]);

        $allocations = $member->depositAllocations
            ->sortBy([
                ['allocation_month', 'asc'],
                ['confirmed_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $availableYears = $allocations
            ->map(fn($allocation): ?int => $allocation->allocation_month?->year)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $selectedYear = $this->resolveCalendarYear($availableYears, $requestedYear);

        $yearAllocations = $allocations
            ->filter(fn($allocation): bool => $allocation->allocation_month?->year === $selectedYear)
            ->values();

        return [
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'relationship_to_user' => $member->relationship_to_user,
                'phone' => $member->phone,
                'units' => $member->units,
                'status' => $member->status->value,
                'manager' => [
                    'name' => $member->manager?->name,
                    'email' => $member->manager?->email,
                ],
            ],
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears->all(),
            'yearlySummary' => [
                'total_units' => (int) $yearAllocations->sum('units'),
                'total_amount' => (int) $yearAllocations->sum('allocated_amount'),
                'paid_months' => $yearAllocations
                    ->map(fn($allocation): ?string => $allocation->allocation_month?->format('Y-m'))
                    ->filter()
                    ->unique()
                    ->count(),
            ],
            'months' => $this->buildMonthGrid($selectedYear, $yearAllocations),
        ];
    }

    protected function buildMonthGrid(int $year, Collection $allocations): array
    {
        return collect(range(1, 12))
            ->map(function (int $month) use ($year, $allocations): array {
                $monthDate = CarbonImmutable::create($year, $month, 1);
                $monthAllocations = $allocations
                    ->filter(fn($allocation): bool => $allocation->allocation_month?->month === $month)
                    ->values();

                return [
                    'month_number' => $month,
                    'month_key' => $monthDate->format('Y-m'),
                    'month_label' => $monthDate->format('F'),
                    'is_paid' => $monthAllocations->isNotEmpty(),
                    'total_units' => (int) $monthAllocations->sum('units'),
                    'total_amount' => (int) $monthAllocations->sum('allocated_amount'),
                    'entries' => $monthAllocations
                        ->map(fn($allocation): array => [
                            'id' => $allocation->id,
                            'units' => $allocation->units,
                            'amount' => $allocation->allocated_amount,
                            'confirmed_at' => $allocation->confirmed_at?->format('d M Y, h:i A'),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    protected function resolveCalendarYear(Collection $availableYears, ?int $requestedYear): int
    {
        if ($requestedYear !== null && $availableYears->contains($requestedYear)) {
            return $requestedYear;
        }

        if ($availableYears->isNotEmpty()) {
            return (int) $availableYears->last();
        }

        return (int) now()->year;
    }
}
