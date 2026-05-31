<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Models\EventOrder;
use App\Models\EventOrderStatusHistory;
use App\Models\EventPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Msilabs\Bkash\BkashPayment;
use Symfony\Component\HttpFoundation\Response;

class EventBkashPaymentService
{
    use BkashPayment;

    public function callbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/bkash/callback';
    }

    public function frontendRedirect(string $status, string $orderNumber, ?string $phone = null): string
    {
        $base = rtrim(config('services.frontend.url'), '/');
        $query = http_build_query(array_filter([
            'status' => $status,
            'order_number' => $orderNumber,
            'phone' => $phone,
        ]));

        return "{$base}/payment/result?{$query}";
    }

    /**
     * @return array{redirect_url: string}
     */
    public function initiate(EventOrder $order): array
    {
        if ($order->status !== EventOrderStatus::Pending) {
            throw new \InvalidArgumentException('This order cannot accept payment.');
        }

        if ((float) $order->advance_amount <= 0) {
            throw new \InvalidArgumentException('No advance payment is required for this order.');
        }

        $merchantInvoice = $this->merchantInvoiceFor($order);

        $response = $this->createPayment(
            (float) $order->advance_amount,
            $merchantInvoice,
            $this->callbackUrl(),
        );

        EventPayment::query()->create([
            'event_order_id' => $order->id,
            'amount' => $order->advance_amount,
            'payment_method' => 'bkash',
            'payment_status' => 'pending',
            'bkash_payment_id' => $response->paymentID,
            'merchant_invoice' => $merchantInvoice,
        ]);

        return [
            'redirect_url' => $response->bkashURL,
        ];
    }

    public function handleCallback(Request $request): Response
    {
        $paymentId = $request->input('paymentID');
        $status = $request->input('status');
        $orderNumber = $request->input('order_number', '');

        if (! $paymentId) {
            return redirect($this->frontendRedirect('failed', $orderNumber ?: ''));
        }

        $payment = EventPayment::query()
            ->where('bkash_payment_id', $paymentId)
            ->where('payment_method', 'bkash')
            ->with('order')
            ->first();

        if (! $payment || ! $payment->order) {
            return redirect($this->frontendRedirect('failed', $orderNumber ?: ''));
        }

        $order = $payment->order;
        $orderNumber = $order->order_number;

        if ($order->status === EventOrderStatus::Confirmed) {
            return redirect($this->frontendRedirect('success', $orderNumber, $order->customer_phone));
        }

        if ($status !== 'success') {
            $this->markPaymentFailed($payment, 'bKash returned status: '.($status ?? 'unknown'));

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        try {
            $response = $this->executePayment($paymentId);
        } catch (\Throwable $e) {
            $this->markPaymentFailed($payment, $e->getMessage());

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        if (($response->transactionStatus ?? null) !== 'Completed') {
            $this->markPaymentFailed($payment, 'Transaction status: '.($response->transactionStatus ?? 'unknown'));

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $invoice = (string) ($response->merchantInvoiceNumber ?? '');
        if ($invoice !== $payment->merchant_invoice) {
            $this->markPaymentFailed($payment, 'Merchant invoice mismatch.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $paidAmount = (float) ($response->amount ?? 0);
        if (round($paidAmount, 2) !== round((float) $order->advance_amount, 2)) {
            $this->markPaymentFailed($payment, 'Paid amount does not match advance amount.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $trxId = (string) ($response->trxID ?? '');

        DB::transaction(function () use ($order, $payment, $trxId): void {
            $order->refresh();

            if ($order->status === EventOrderStatus::Confirmed) {
                return;
            }

            $now = now();

            $payment->update([
                'payment_status' => 'verified',
                'transaction_reference' => $trxId,
                'paid_at' => $now,
                'verified_at' => $now,
            ]);

            $order->update([
                'status' => EventOrderStatus::Confirmed,
                'confirmed_at' => $now,
            ]);

            EventOrderStatusHistory::create([
                'event_order_id' => $order->id,
                'status' => EventOrderStatus::Confirmed->value,
                'note' => 'Advance paid via bKash'.($trxId ? " (trx: {$trxId})" : '').'.',
                'changed_by_user_id' => null,
                'changed_at' => $now,
            ]);
        });

        return redirect($this->frontendRedirect('success', $orderNumber, $order->customer_phone));
    }

    public function confirmOrderWithoutPayment(EventOrder $order): void
    {
        if ($order->status !== EventOrderStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $now = now();

            $order->update([
                'status' => EventOrderStatus::Confirmed,
                'confirmed_at' => $now,
            ]);

            EventOrderStatusHistory::create([
                'event_order_id' => $order->id,
                'status' => EventOrderStatus::Confirmed->value,
                'note' => 'Order confirmed (no advance payment required).',
                'changed_by_user_id' => null,
                'changed_at' => $now,
            ]);
        });
    }

    private function merchantInvoiceFor(EventOrder $order): string
    {
        return 'ISF-'.$order->id.'-'.Str::lower(Str::random(8));
    }

    private function markPaymentFailed(EventPayment $payment, string $note): void
    {
        if ($payment->payment_status === 'verified') {
            return;
        }

        $payment->update([
            'payment_status' => 'failed',
            'note' => Str::limit($note, 500),
        ]);
    }
}
