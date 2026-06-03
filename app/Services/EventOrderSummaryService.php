<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageUnitType;
use App\Models\EventOrder;
use App\Models\EventOrderItem;
use App\Models\EventPackage;
use App\Models\EventPayment;
use App\Models\EventPickupPoint;
use App\Models\FundCycleEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EventOrderSummaryService
{
    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'verified', 'failed'];

    /**
     * @return array<string, mixed>
     */
    public function forEvent(FundCycleEvent $fundCycleEvent): array
    {
        $eventId = $fundCycleEvent->id;
        $baseQuery = fn (): Builder => EventOrder::query()->where('fund_cycle_event_id', $eventId);

        $statusCounts = $baseQuery()
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $dueExpression = EventOrder::dueAmountSqlExpression();
        $dueExpressionForGroupBy = EventOrder::dueAmountWithJoinSqlExpression();

        $moneyRow = $baseQuery()
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_order_amount')
            ->selectRaw('COALESCE(SUM(advance_amount), 0) as total_advance_amount')
            ->selectRaw("COALESCE(SUM({$dueExpression}), 0) as total_due_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$dueExpression} > 0 THEN 1 ELSE 0 END), 0) as orders_with_due_count")
            ->first();

        $pickupPoints = $fundCycleEvent->pickupPoints()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $pickupAggregates = $this->confirmedOrdersQuery($eventId)
            ->withVerifiedPaidSum()
            ->selectRaw('event_pickup_point_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw("COALESCE(SUM({$dueExpressionForGroupBy}), 0) as total_due_amount")
            ->groupBy('event_pickup_point_id')
            ->get()
            ->keyBy('event_pickup_point_id');

        $pickupStatusCounts = $baseQuery()
            ->selectRaw('event_pickup_point_id')
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('event_pickup_point_id', 'status')
            ->get()
            ->groupBy('event_pickup_point_id');

        $pickupPackageQuantities = EventOrderItem::query()
            ->join('event_orders', 'event_orders.id', '=', 'event_order_items.event_order_id')
            ->where('event_orders.fund_cycle_event_id', $eventId)
            ->where('event_orders.status', EventOrderStatus::Confirmed)
            ->whereNotNull('event_orders.event_pickup_point_id')
            ->selectRaw('event_orders.event_pickup_point_id')
            ->selectRaw('event_order_items.event_package_id')
            ->selectRaw('SUM(event_order_items.quantity) as total_quantity')
            ->groupBy('event_orders.event_pickup_point_id', 'event_order_items.event_package_id')
            ->get()
            ->groupBy('event_pickup_point_id');

        $packageStatusCounts = EventOrderItem::query()
            ->join('event_orders', 'event_orders.id', '=', 'event_order_items.event_order_id')
            ->where('event_orders.fund_cycle_event_id', $eventId)
            ->selectRaw('event_order_items.event_package_id')
            ->selectRaw('event_orders.status')
            ->selectRaw('COUNT(DISTINCT event_orders.id) as order_count')
            ->groupBy('event_order_items.event_package_id', 'event_orders.status')
            ->get()
            ->groupBy('event_package_id');

        $packages = $fundCycleEvent->packages()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $packageOrderAggregates = EventOrderItem::query()
            ->join('event_orders', 'event_orders.id', '=', 'event_order_items.event_order_id')
            ->where('event_orders.fund_cycle_event_id', $eventId)
            ->where('event_orders.status', EventOrderStatus::Confirmed)
            ->selectRaw('event_order_items.event_package_id')
            ->selectRaw('SUM(event_order_items.quantity) as pack_count')
            ->selectRaw(
                'SUM(CAST(event_order_items.unit_size AS DECIMAL(12,3)) * event_order_items.quantity) as physical_total',
            )
            ->groupBy('event_order_items.event_package_id')
            ->get()
            ->keyBy('event_package_id');

        return [
            'orders' => [
                'total' => (int) $baseQuery()->count(),
                'today' => (int) $baseQuery()->whereDate('created_at', today())->count(),
                'last_7_days' => (int) $baseQuery()->where('created_at', '>=', now()->subDays(7))->count(),
                'by_status' => collect(EventOrderStatus::cases())
                    ->mapWithKeys(fn (EventOrderStatus $status): array => [
                        $status->value => (int) ($statusCounts[$status->value] ?? 0),
                    ])
                    ->all(),
            ],
            'money' => [
                'total_order_amount' => $this->formatMoney($moneyRow->total_order_amount ?? 0),
                'total_advance_amount' => $this->formatMoney($moneyRow->total_advance_amount ?? 0),
                'total_due_amount' => $this->formatMoney($moneyRow->total_due_amount ?? 0),
                'orders_with_due_count' => (int) ($moneyRow->orders_with_due_count ?? 0),
            ],
            'payments' => [
                ...collect(self::PAYMENT_STATUSES)
                    ->mapWithKeys(fn (string $paymentStatus): array => [
                        $paymentStatus => $this->countByPaymentStatus($eventId, $paymentStatus),
                    ])
                    ->all(),
                'verified_amount' => $this->formatMoney(
                    EventPayment::query()
                        ->where('payment_status', 'verified')
                        ->whereHas(
                            'order',
                            fn (Builder $query) => $query->where('fund_cycle_event_id', $eventId),
                        )
                        ->sum('amount'),
                ),
            ],
            'focus' => $this->buildFocusSummary($eventId, $statusCounts),
            'pickup_points' => $this->formatPickupBreakdown(
                $pickupPoints,
                $pickupAggregates,
                $pickupStatusCounts,
                $pickupPackageQuantities,
                $packages,
            ),
            'packages' => $this->formatPackageSnapshot(
                $packages,
                $packageStatusCounts,
                $packageOrderAggregates,
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyByStatus(): array
    {
        return collect(EventOrderStatus::cases())
            ->mapWithKeys(fn (EventOrderStatus $status): array => [
                $status->value => 0,
            ])
            ->all();
    }

    /**
     * @param  Collection<int|string, Collection<int, Model>>  $statusCountsByGroup
     * @return array<string, int>
     */
    private function statusCountsForGroup(
        Collection $statusCountsByGroup,
        int|string|null $groupId,
    ): array {
        $counts = $this->emptyByStatus();

        if ($groupId === null) {
            return $counts;
        }

        foreach ($statusCountsByGroup->get($groupId, []) as $row) {
            $status = $row->status instanceof EventOrderStatus
                ? $row->status->value
                : (string) $row->status;

            if (array_key_exists($status, $counts)) {
                $countColumn = isset($row->aggregate_count)
                    ? 'aggregate_count'
                    : 'order_count';
                $counts[$status] = (int) $row->{$countColumn};
            }
        }

        return $counts;
    }

    private function confirmedOrdersQuery(int $eventId): Builder
    {
        return EventOrder::query()
            ->where('fund_cycle_event_id', $eventId)
            ->where('status', EventOrderStatus::Confirmed);
    }

    private function countByPaymentStatus(int $eventId, string $paymentStatus): int
    {
        $query = EventOrder::query()->where('fund_cycle_event_id', $eventId);
        $this->applyPaymentStatusFilter($query, $paymentStatus);

        return (int) $query->count();
    }

    private function applyPaymentStatusFilter(Builder $query, string $paymentStatus): void
    {
        if ($paymentStatus === 'unpaid') {
            $query->whereDoesntHave('payments');

            return;
        }

        $query->whereHas('payments', function (Builder $paymentQuery) use ($paymentStatus): void {
            $paymentQuery
                ->where('payment_status', $paymentStatus)
                ->whereRaw(
                    'id = (select max(ep.id) from event_payments as ep where ep.event_order_id = event_orders.id)',
                );
        });
    }

    /**
     * @param  Collection<int, EventPickupPoint>  $pickupPoints
     * @param  Collection<int|string, Model>  $pickupAggregates
     * @param  Collection<int|string, Collection<int, Model>>  $pickupStatusCounts
     * @param  Collection<int|string, Collection<int, Model>>  $pickupPackageQuantities
     * @param  Collection<int, EventPackage>  $packages
     * @return list<array<string, array<string, int>|int|list<array<string, int|string>>|string|null>>
     */
    private function formatPickupBreakdown(
        Collection $pickupPoints,
        Collection $pickupAggregates,
        Collection $pickupStatusCounts,
        Collection $pickupPackageQuantities,
        Collection $packages,
    ): array {
        return $pickupPoints
            ->map(function (EventPickupPoint $point) use (
                $pickupAggregates,
                $pickupStatusCounts,
                $pickupPackageQuantities,
                $packages,
            ): array {
                $row = $pickupAggregates->get($point->id);
                $byStatus = $this->statusCountsForGroup($pickupStatusCounts, $point->id);

                return [
                    'id' => $point->id,
                    'name' => $point->name,
                    'order_count' => (int) ($row->order_count ?? 0),
                    'by_status' => $byStatus,
                    'packages' => $this->formatPickupPackageQuantities(
                        $point->id,
                        $pickupPackageQuantities,
                        $packages,
                    ),
                    'total_due_amount' => $this->formatMoney($row->total_due_amount ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string, Collection<int, Model>>  $pickupPackageQuantities
     * @param  Collection<int, EventPackage>  $packages
     * @return list<array<string, int|string>>
     */
    private function formatPickupPackageQuantities(
        int $pickupPointId,
        Collection $pickupPackageQuantities,
        Collection $packages,
    ): array {
        $quantityByPackageId = $pickupPackageQuantities
            ->get($pickupPointId, collect())
            ->keyBy('event_package_id');

        return $packages
            ->map(function (EventPackage $package) use ($quantityByPackageId): ?array {
                $quantity = (int) ($quantityByPackageId->get($package->id)?->total_quantity ?? 0);

                if ($quantity === 0) {
                    return null;
                }

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'quantity' => $quantity,
                    'unit_label' => $package->unitLabel(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, EventPackage>  $packages
     * @param  Collection<int|string, Collection<int, Model>>  $packageStatusCounts
     * @param  Collection<int|string, Model>  $packageOrderAggregates
     * @return list<array<string, array<string, int>|bool|int|string|null>>
     */
    private function formatPackageSnapshot(
        Collection $packages,
        Collection $packageStatusCounts,
        Collection $packageOrderAggregates,
    ): array {
        return $packages
            ->map(function (EventPackage $package) use (
                $packageStatusCounts,
                $packageOrderAggregates,
            ): array {
                $byStatus = $this->statusCountsForGroup($packageStatusCounts, $package->id);
                $orderRow = $packageOrderAggregates->get($package->id);
                $packCount = (int) ($orderRow->pack_count ?? 0);
                $physicalTotal = (float) ($orderRow->physical_total ?? 0);

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'sold_qty' => $package->sold_qty,
                    'stock_qty' => $package->stock_qty,
                    'remaining_qty' => $package->remainingQty(),
                    'order_count' => (int) ($byStatus[EventOrderStatus::Confirmed->value] ?? 0),
                    'by_status' => $byStatus,
                    'pack_count' => $packCount,
                    'physical_label' => $packCount > 0
                        ? $package->unit_type->formatSize($physicalTotal)
                        : null,
                    'pack_line_label' => $packCount > 0
                        ? EventPackageUnitType::formatPackLine(
                            $package->unit_size,
                            $package->unit_type,
                            $packCount,
                        )
                        : null,
                    'is_low_stock' => $package->stock_qty !== null
                        && $package->remainingQty() !== null
                        && $package->remainingQty() <= 5,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, int|string>  $statusCounts
     * @return array<string, int|string>
     */
    private function buildFocusSummary(int $eventId, Collection $statusCounts): array
    {
        $dueExpression = EventOrder::dueAmountSqlExpression();

        $confirmedQuery = EventOrder::query()
            ->where('fund_cycle_event_id', $eventId)
            ->where('status', EventOrderStatus::Confirmed);

        $confirmedMoney = (clone $confirmedQuery)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(advance_amount), 0) as advance_amount')
            ->selectRaw("COALESCE(SUM({$dueExpression}), 0) as total_due_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$dueExpression} > 0 THEN 1 ELSE 0 END), 0) as orders_with_due_count")
            ->first();

        $verifiedPaymentCount = $this->countByPaymentStatus($eventId, 'verified');

        $confirmedWithVerifiedPayment = EventOrder::query()
            ->where('fund_cycle_event_id', $eventId)
            ->where('status', EventOrderStatus::Confirmed)
            ->whereHas('payments', function (Builder $paymentQuery): void {
                $paymentQuery
                    ->where('payment_status', 'verified')
                    ->whereRaw(
                        'id = (select max(ep.id) from event_payments as ep where ep.event_order_id = event_orders.id)',
                    );
            })
            ->count();

        return [
            'verified_amount' => $this->formatMoney(
                EventPayment::query()
                    ->where('payment_status', 'verified')
                    ->whereHas(
                        'order',
                        fn (Builder $query) => $query->where('fund_cycle_event_id', $eventId),
                    )
                    ->sum('amount'),
            ),
            'confirmed_order_count' => (int) ($statusCounts[EventOrderStatus::Confirmed->value] ?? 0),
            'confirmed_order_amount' => $this->formatMoney($confirmedMoney->total_amount ?? 0),
            'confirmed_due_amount' => $this->formatMoney($confirmedMoney->total_due_amount ?? 0),
            'confirmed_orders_with_due_count' => (int) ($confirmedMoney->orders_with_due_count ?? 0),
            'confirmed_advance_amount' => $this->formatMoney($confirmedMoney->advance_amount ?? 0),
            'confirmed_verified_payment_count' => (int) $confirmedWithVerifiedPayment,
            'verified_payment_count' => $verifiedPaymentCount,
            'pending_order_count' => (int) ($statusCounts[EventOrderStatus::Pending->value] ?? 0),
            'delivered_order_count' => (int) ($statusCounts[EventOrderStatus::Delivered->value] ?? 0),
            'cancelled_order_count' => (int) ($statusCounts[EventOrderStatus::Cancelled->value] ?? 0),
        ];
    }

    private function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
