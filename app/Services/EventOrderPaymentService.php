<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Enums\EventPaymentType;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EventOrderPaymentService
{
    public function recordManualPayment(
        EventOrder $order,
        float $amount,
        string $paymentMethod,
        ?string $transactionReference,
        ?string $note,
    ): EventPayment {
        $this->assertCanAcceptPayment($order);

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($amount > $order->dueAmount()) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount cannot exceed the order due balance.',
            ]);
        }

        return EventPayment::query()->create([
            'event_order_id' => $order->id,
            'amount' => $amount,
            'payment_type' => EventPaymentType::Manual,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'transaction_reference' => $transactionReference,
            'note' => $note,
        ]);
    }

    public function verifyPayment(EventPayment $payment, User $verifiedBy): EventPayment
    {
        if ($payment->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'payment' => 'Only pending payments can be verified.',
            ]);
        }

        $order = $payment->order;

        if (! $order) {
            throw ValidationException::withMessages([
                'payment' => 'Order not found for this payment.',
            ]);
        }

        $this->assertCanAcceptPayment($order);

        if ((float) $payment->amount > $order->dueAmount()) {
            throw ValidationException::withMessages([
                'payment' => 'Verified amount would exceed the order due balance.',
            ]);
        }

        $now = now();

        $payment->update([
            'payment_status' => 'verified',
            'paid_at' => $now,
            'verified_at' => $now,
            'verified_by_user_id' => $verifiedBy->id,
        ]);

        return $payment->refresh();
    }

    public function rejectPayment(EventPayment $payment, User $rejectedBy, ?string $reason): EventPayment
    {
        if ($payment->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'payment' => 'Only pending payments can be rejected.',
            ]);
        }

        $payment->update([
            'payment_status' => 'failed',
            'note' => $reason ? trim($reason) : 'Rejected by admin.',
            'verified_by_user_id' => $rejectedBy->id,
            'verified_at' => now(),
        ]);

        return $payment->refresh();
    }

    private function assertCanAcceptPayment(EventOrder $order): void
    {
        if (! $order->canAcceptDuePayment()) {
            throw ValidationException::withMessages([
                'order' => match (true) {
                    $order->status === EventOrderStatus::Cancelled => 'Cancelled orders cannot accept payments.',
                    $order->status === EventOrderStatus::Pending => 'Confirm the order before recording due payments.',
                    $order->dueAmount() <= 0 => 'This order has no due balance remaining.',
                    default => 'This order cannot accept due payments.',
                },
            ]);
        }
    }
}
