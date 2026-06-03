<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventOrderStatus;
use App\Enums\EventPackageUnitType;
use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use App\Models\FundCycleEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventOrderController extends Controller
{
    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'verified', 'failed'];

    public function index(Request $request, FundCycleEvent $fundCycleEvent): Response
    {
        $status = $request->string('status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        $search = trim($request->string('search')->toString());
        $pickupPointId = $request->integer('pickup_point_id');
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();
        $hasDue = $request->boolean('has_due');
        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $validPickupPointIds = $fundCycleEvent->pickupPoints()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $ordersQuery = EventOrder::query()
            ->where('fund_cycle_event_id', $fundCycleEvent->id)
            ->with(['payments', 'items', 'pickupPoint:id,name,contact_person'])
            ->when(
                in_array($status, EventOrderStatus::values(), true),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when(
                in_array($paymentStatus, self::PAYMENT_STATUSES, true),
                fn (Builder $query) => $this->applyPaymentStatusFilter($query, $paymentStatus),
            )
            ->when(
                in_array($pickupPointId, $validPickupPointIds, true),
                fn (Builder $query) => $query->where('event_pickup_point_id', $pickupPointId),
            )
            ->when($fromDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $toDate))
            ->when($hasDue, fn (Builder $query) => $query->whereColumn('total_amount', '>', 'advance_amount'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $pickupPoints = $fundCycleEvent->pickupPoints()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        return Inertia::render('admin/EventOrders', [
            'event' => [
                'id' => $fundCycleEvent->id,
                'title' => $fundCycleEvent->title,
                'slug' => $fundCycleEvent->slug,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'pickup_point_id' => in_array($pickupPointId, $validPickupPointIds, true) ? (string) $pickupPointId : '',
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'has_due' => $hasDue,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => EventOrderStatus::options(),
                'payment_statuses' => [
                    ['value' => 'unpaid', 'label' => 'Unpaid'],
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'failed', 'label' => 'Failed'],
                ],
                'pickup_points' => $pickupPoints
                    ->map(fn ($point): array => [
                        'id' => $point->id,
                        'name' => $point->name,
                    ])
                    ->values(),
            ],
            'orders' => $ordersQuery
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (EventOrder $order): array => $this->formatOrderListItem($order)),
        ]);
    }

    public function show(FundCycleEvent $fundCycleEvent, EventOrder $eventOrder): Response
    {
        abort_unless($eventOrder->fund_cycle_event_id === $fundCycleEvent->id, 404);

        $eventOrder->load([
            'items.package:id,name',
            'pickupPoint:id,name,area,address,contact_person,phone',
            'payments' => fn ($query) => $query->latest('id'),
            'statusHistories.changedBy:id,name',
        ]);

        return Inertia::render('admin/EventOrderDetails', [
            'event' => [
                'id' => $fundCycleEvent->id,
                'title' => $fundCycleEvent->title,
                'slug' => $fundCycleEvent->slug,
            ],
            'order' => [
                'id' => $eventOrder->id,
                'order_number' => $eventOrder->order_number,
                'customer_name' => $eventOrder->customer_name,
                'customer_phone' => $eventOrder->customer_phone,
                'customer_address' => $eventOrder->customer_address,
                'status' => $eventOrder->status->value,
                'status_label' => $eventOrder->status->label(),
                'total_amount' => (string) $eventOrder->total_amount,
                'advance_amount' => (string) $eventOrder->advance_amount,
                'due_amount' => (string) $eventOrder->dueAmount(),
                'created_at' => $eventOrder->created_at?->format('d M Y, h:i A'),
                'confirmed_at' => $eventOrder->confirmed_at?->format('d M Y, h:i A'),
                'pickup_point' => $eventOrder->pickupPoint ? [
                    'name' => $eventOrder->pickupPoint->name,
                    'area' => $eventOrder->pickupPoint->area,
                    'address' => $eventOrder->pickupPoint->address,
                    'contact_person' => $eventOrder->pickupPoint->contact_person,
                    'phone' => $eventOrder->pickupPoint->phone,
                ] : null,
                'items' => $eventOrder->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'package_name' => $item->package?->name ?? '-',
                    'quantity' => $item->quantity,
                    'unit_type' => $item->unit_type?->value,
                    'unit_size' => $item->unit_size !== null ? (string) $item->unit_size : null,
                    'unit_label' => $item->unitLabel(),
                    'quantity_label' => $item->quantityLabel(),
                    'physical_quantity' => $item->physicalQuantity(),
                    'package_price' => (string) $item->package_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
                'payments' => $eventOrder->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'amount' => (string) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_reference' => $payment->transaction_reference,
                    'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
                    'verified_at' => $payment->verified_at?->format('d M Y, h:i A'),
                ])->values(),
                'status_histories' => $eventOrder->statusHistories
                    ->sortByDesc('changed_at')
                    ->values()
                    ->map(fn ($history): array => [
                        'id' => $history->id,
                        'status' => $history->status,
                        'note' => $history->note,
                        'changed_at' => $history->changed_at?->format('d M Y, h:i A'),
                        'changed_by' => $history->changedBy?->name,
                    ]),
            ],
        ]);
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

    private function formatOrderListItem(EventOrder $order): array
    {
        $latestPayment = $order->payments->sortByDesc('id')->first();
        $totalsByUnit = EventPackageUnitType::totalsByUnitType(
            $order->items->map(fn ($item): array => [
                'unit_type' => $item->unit_type?->value ?? EventPackageUnitType::Piece->value,
                'unit_size' => $item->unit_size ?? 1,
                'quantity' => $item->quantity,
            ]),
        );

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'total_amount' => (string) $order->total_amount,
            'advance_amount' => (string) $order->advance_amount,
            'due_amount' => (string) $order->dueAmount(),
            'total_packs' => $order->items->sum('quantity'),
            'quantity_summary' => EventPackageUnitType::formatTotalsSummary($totalsByUnit),
            'payment_status' => $latestPayment?->payment_status ?? 'unpaid',
            'pickup_point' => $order->pickupPoint ? [
                'name' => $order->pickupPoint->name,
                'contact_person' => $order->pickupPoint->contact_person,
            ] : null,
            'created_at' => $order->created_at?->format('d M Y, h:i A'),
        ];
    }
}
