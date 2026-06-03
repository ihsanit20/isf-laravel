<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Models\EventOrder;
use App\Models\EventOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventOrderStatusService
{
    public function __construct(
        private readonly EventOrderConfirmationService $orderConfirmation,
    ) {}

    public function update(
        EventOrder $order,
        EventOrderStatus $status,
        string $note,
        ?User $changedBy = null,
        bool $allowDeliveredWithDue = false,
    ): EventOrder {
        $note = trim($note);

        if ($order->status === $status) {
            throw ValidationException::withMessages([
                'status' => 'Order is already in this status.',
            ]);
        }

        if ($status === EventOrderStatus::Cancelled && $note === '') {
            throw ValidationException::withMessages([
                'note' => 'A note is required when cancelling an order.',
            ]);
        }

        if ($status === EventOrderStatus::Delivered && $order->dueAmount() > 0 && ! $allowDeliveredWithDue) {
            throw ValidationException::withMessages([
                'status' => 'Clear the due balance before marking as delivered, or enable override.',
            ]);
        }

        if ($status === EventOrderStatus::Confirmed && $order->status !== EventOrderStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending orders can be confirmed from admin.',
            ]);
        }

        if ($status === EventOrderStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Orders cannot be moved back to pending.',
            ]);
        }

        if ($status === EventOrderStatus::Confirmed && $order->status === EventOrderStatus::Pending) {
            $this->orderConfirmation->confirm(
                $order,
                $note !== '' ? $note : 'Order confirmed by admin.',
            );

            if ($changedBy) {
                EventOrderStatusHistory::query()
                    ->where('event_order_id', $order->id)
                    ->latest('id')
                    ->limit(1)
                    ->update(['changed_by_user_id' => $changedBy->id]);
            }

            return $order->refresh();
        }

        DB::transaction(function () use ($order, $status, $note, $changedBy): void {
            $order->refresh();
            $now = now();

            $attributes = ['status' => $status];

            if ($status === EventOrderStatus::Confirmed && ! $order->confirmed_at) {
                $attributes['confirmed_at'] = $now;
                EventOrder::ensureTrackingToken($order);
            }

            $order->update($attributes);

            EventOrderStatusHistory::create([
                'event_order_id' => $order->id,
                'status' => $status->value,
                'note' => $note !== '' ? $note : null,
                'changed_by_user_id' => $changedBy?->id,
                'changed_at' => $now,
            ]);
        });

        return $order->refresh();
    }
}
