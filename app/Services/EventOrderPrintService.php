<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\EventPickupPoint;
use App\Models\FundCycleEvent;
use App\Support\EventOrderTrackingUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventOrderPrintService
{
    public function __construct(
        private readonly EventOrderSummaryService $eventOrderSummary,
    ) {}

    /**
     * @return array{
     *     event: array<string, mixed>,
     *     status_filter: string,
     *     status_label: string,
     *     generated_at: string,
     *     sections: list<array<string, mixed>>
     * }
     */
    public function pickupDispatchList(
        FundCycleEvent $fundCycleEvent,
        ?EventPickupPoint $pickupPoint = null,
        string $statusFilter = 'confirmed',
    ): array {
        $this->assertPickupPointBelongsToEvent($fundCycleEvent, $pickupPoint);

        $pickupPoints = $this->resolvePickupPoints($fundCycleEvent, $pickupPoint);

        return [
            'event' => $this->formatEventHeader($fundCycleEvent),
            'status_filter' => $statusFilter,
            'status_label' => $this->statusFilterLabel($statusFilter),
            'generated_at' => now()->format('d M Y, h:i A'),
            'sections' => $pickupPoints
                ->map(fn (EventPickupPoint $point): array => $this->formatPickupSection(
                    $fundCycleEvent,
                    $point,
                    $statusFilter,
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerReceipt(EventOrder $order): array
    {
        $order->load([
            'items.package:id,name',
            'pickupPoint:id,name,area,address,contact_person,phone',
            'fundCycleEvent:id,title,expected_delivery_date',
        ]);

        return [
            'event' => $this->formatEventHeader($order->fundCycleEvent),
            'generated_at' => now()->format('d M Y, h:i A'),
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_address' => $order->customer_address,
                'created_at' => $order->created_at?->format('d M Y, h:i A'),
                'confirmed_at' => $order->confirmed_at?->format('d M Y, h:i A'),
                'total_amount' => $this->formatMoney($order->total_amount),
                'advance_amount' => $this->formatMoney($order->advance_amount),
                'verified_paid_amount' => $this->formatMoney($order->totalVerifiedPaid()),
                'due_amount' => $this->formatMoney($order->dueAmount()),
                'tracking_url' => EventOrderTrackingUrl::short($order),
                'pickup_point' => $order->pickupPoint ? [
                    'name' => $order->pickupPoint->name,
                    'area' => $order->pickupPoint->area,
                    'address' => $order->pickupPoint->address,
                    'contact_person' => $order->pickupPoint->contact_person,
                    'phone' => $order->pickupPoint->phone,
                ] : null,
                'items' => $order->items->map(fn ($item): array => [
                    'package_name' => $item->package?->name ?? '-',
                    'quantity_label' => $item->quantityLabel(),
                    'line_total' => $this->formatMoney($item->line_total),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentReceipt(EventOrder $order, EventPayment $payment): array
    {
        $order->load([
            'fundCycleEvent:id,title,expected_delivery_date',
        ]);

        return [
            'event' => $this->formatEventHeader($order->fundCycleEvent),
            'generated_at' => now()->format('d M Y, h:i A'),
            'payment' => [
                'reference' => 'PAY-'.$payment->id,
                'amount' => $this->formatMoney($payment->amount),
                'payment_type_label' => $payment->payment_type?->labelBn() ?? 'পেমেন্ট',
                'payment_method' => $payment->payment_method,
                'transaction_reference' => $payment->transaction_reference,
                'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
                'verified_at' => $payment->verified_at?->format('d M Y, h:i A'),
            ],
            'order' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'total_amount' => $this->formatMoney($order->total_amount),
                'verified_paid_amount' => $this->formatMoney($order->totalVerifiedPaid()),
                'due_amount' => $this->formatMoney($order->dueAmount()),
            ],
        ];
    }

    /**
     * @return array{
     *     event: array<string, mixed>,
     *     generated_at: string,
     *     packages: list<array<string, mixed>>
     * }
     */
    public function packagePackingSummary(FundCycleEvent $fundCycleEvent): array
    {
        $summary = $this->eventOrderSummary->forEvent($fundCycleEvent);

        return [
            'event' => $this->formatEventHeader($fundCycleEvent),
            'generated_at' => now()->format('d M Y, h:i A'),
            'packages' => collect($summary['packages'])
                ->filter(fn (array $pkg): bool => ($pkg['pack_count'] ?? 0) > 0)
                ->map(fn (array $pkg): array => [
                    'name' => $pkg['name'],
                    'order_count' => $pkg['order_count'],
                    'pack_count' => $pkg['pack_count'],
                    'physical_label' => $pkg['physical_label'],
                    'pack_line_label' => $pkg['pack_line_label'],
                ])
                ->values()
                ->all(),
        ];
    }

    public function parseStatusFilter(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if (in_array($status, ['confirmed', 'pending', 'all'], true)) {
            return $status;
        }

        return 'confirmed';
    }

    /**
     * @return Collection<int, EventPickupPoint>
     */
    private function resolvePickupPoints(
        FundCycleEvent $fundCycleEvent,
        ?EventPickupPoint $pickupPoint,
    ): Collection {
        if ($pickupPoint) {
            return collect([$pickupPoint]);
        }

        return $fundCycleEvent->pickupPoints()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPickupSection(
        FundCycleEvent $fundCycleEvent,
        EventPickupPoint $pickupPoint,
        string $statusFilter,
    ): array {
        $orders = $this->ordersQuery($fundCycleEvent, $statusFilter)
            ->where('event_pickup_point_id', $pickupPoint->id)
            ->with(['items.package:id,name'])
            ->orderBy('order_number')
            ->get();

        return [
            'pickup_point' => [
                'id' => $pickupPoint->id,
                'name' => $pickupPoint->name,
                'area' => $pickupPoint->area,
                'address' => $pickupPoint->address,
                'contact_person' => $pickupPoint->contact_person,
                'phone' => $pickupPoint->phone,
            ],
            'order_count' => $orders->count(),
            'total_due_amount' => $this->formatMoney($orders->sum(fn (EventOrder $order): float => $order->dueAmount())),
            'orders' => $orders->map(fn (EventOrder $order): array => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'status_label' => $order->status->label(),
                'total_amount' => $this->formatMoney($order->total_amount),
                'due_amount' => $this->formatMoney($order->dueAmount()),
                'items_label' => $order->items
                    ->map(fn ($item): string => ($item->package?->name ?? '-').' × '.$item->quantityLabel())
                    ->implode('; '),
            ])->values()->all(),
        ];
    }

    private function ordersQuery(FundCycleEvent $fundCycleEvent, string $statusFilter): Builder
    {
        $query = EventOrder::query()
            ->where('fund_cycle_event_id', $fundCycleEvent->id);

        if ($statusFilter === 'confirmed') {
            $query->where('status', EventOrderStatus::Confirmed);
        } elseif ($statusFilter === 'pending') {
            $query->where('status', EventOrderStatus::Pending);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEventHeader(FundCycleEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'expected_delivery_date' => $event->expected_delivery_date?->format('d M Y'),
            'order_open_at' => $event->order_open_at?->format('d M Y'),
            'order_close_at' => $event->order_close_at?->format('d M Y'),
        ];
    }

    private function statusFilterLabel(string $statusFilter): string
    {
        return match ($statusFilter) {
            'pending' => 'Pending অর্ডার',
            'all' => 'সব অর্ডার',
            default => 'Confirmed অর্ডার',
        };
    }

    private function assertPickupPointBelongsToEvent(
        FundCycleEvent $fundCycleEvent,
        ?EventPickupPoint $pickupPoint,
    ): void {
        if (! $pickupPoint) {
            return;
        }

        if ($pickupPoint->fund_cycle_event_id !== $fundCycleEvent->id) {
            throw ValidationException::withMessages([
                'pickup_point' => 'Pickup point does not belong to this event.',
            ]);
        }
    }

    private function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
