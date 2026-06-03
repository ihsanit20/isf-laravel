<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Enums\EventPaymentType;
use App\Models\EventOrder;
use App\Models\EventPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Msilabs\Bkash\BkashPayment;
use Symfony\Component\HttpFoundation\Response;

class EventBkashPaymentService
{
    use BkashPayment;

    public function __construct(
        private readonly EventOrderConfirmationService $orderConfirmation,
    ) {}

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
    public function initiateAdvance(EventOrder $order): array
    {
        if ($order->status !== EventOrderStatus::Pending) {
            throw new \InvalidArgumentException('This order cannot accept advance payment.');
        }

        if ((float) $order->advance_amount <= 0) {
            throw new \InvalidArgumentException('No advance payment is required for this order.');
        }

        if ($order->hasVerifiedAdvancePayment()) {
            throw new \InvalidArgumentException('Advance payment is already verified for this order.');
        }

        return $this->createBkashPayment(
            $order,
            (float) $order->advance_amount,
            EventPaymentType::Advance,
        );
    }

    /**
     * @return array{redirect_url: string}
     */
    public function initiateDue(EventOrder $order): array
    {
        if ($order->status !== EventOrderStatus::Confirmed) {
            throw new \InvalidArgumentException('Due payment is only available for confirmed orders.');
        }

        $dueAmount = $order->dueAmount();

        if ($dueAmount <= 0) {
            throw new \InvalidArgumentException('This order has no due balance.');
        }

        if ($order->payments()->where('payment_status', 'pending')->exists()) {
            throw new \InvalidArgumentException('A payment is already pending for this order.');
        }

        return $this->createBkashPayment($order, $dueAmount, EventPaymentType::Due);
    }

    /**
     * @deprecated Use initiateAdvance()
     *
     * @return array{redirect_url: string}
     */
    public function initiate(EventOrder $order): array
    {
        return $this->initiateAdvance($order);
    }

    public function handleCallback(Request $request): Response
    {
        $paymentId = $request->input('paymentID');
        $status = $request->input('status');
        $orderNumber = $request->string('order_number')->toString();

        if (! $paymentId) {
            return redirect($this->frontendRedirect('failed', $orderNumber));
        }

        $payment = EventPayment::query()
            ->where('bkash_payment_id', $paymentId)
            ->where('payment_method', 'bkash')
            ->with('order')
            ->first();

        if (! $payment || ! $payment->order) {
            return redirect($this->frontendRedirect('failed', $orderNumber));
        }

        $order = $payment->order;
        $orderNumber = $order->order_number;
        $paymentType = $payment->payment_type ?? EventPaymentType::Advance;

        if ($payment->payment_status === 'verified') {
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
        if (round($paidAmount, 2) !== round((float) $payment->amount, 2)) {
            $this->markPaymentFailed($payment, 'Paid amount does not match expected amount.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $trxId = (string) ($response->trxID ?? '');

        if ($paymentType === EventPaymentType::Due) {
            $this->verifyDuePayment($payment, $trxId);

            return redirect($this->frontendRedirect('success', $orderNumber, $order->customer_phone));
        }

        return $this->completeAdvanceCallback($order, $payment, $trxId, $orderNumber);
    }

    public function confirmOrderWithoutPayment(EventOrder $order): void
    {
        $this->orderConfirmation->confirm(
            $order,
            'Order confirmed (no advance payment required).',
        );
    }

    /**
     * @return array{redirect_url: string}
     */
    private function createBkashPayment(EventOrder $order, float $amount, EventPaymentType $paymentType): array
    {
        $merchantInvoice = $this->merchantInvoiceFor($order, $paymentType);

        $response = $this->createPayment(
            $amount,
            $merchantInvoice,
            $this->callbackUrl(),
        );

        EventPayment::query()->create([
            'event_order_id' => $order->id,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'payment_method' => 'bkash',
            'payment_status' => 'pending',
            'bkash_payment_id' => $response->paymentID,
            'merchant_invoice' => $merchantInvoice,
        ]);

        return [
            'redirect_url' => $response->bkashURL,
        ];
    }

    private function completeAdvanceCallback(
        EventOrder $order,
        EventPayment $payment,
        string $trxId,
        string $orderNumber,
    ): Response {
        if ($order->status === EventOrderStatus::Confirmed) {
            return redirect($this->frontendRedirect('success', $orderNumber, $order->customer_phone));
        }

        $confirmed = false;

        DB::transaction(function () use ($order, $payment, $trxId, &$confirmed): void {
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

            $confirmed = $this->orderConfirmation->markConfirmed(
                $order,
                'Advance paid via bKash'.($trxId ? " (trx: {$trxId})" : '').'.',
            );
        });

        if ($confirmed) {
            $order->refresh();
            $this->orderConfirmation->sendConfirmationSms($order);
        }

        return redirect($this->frontendRedirect('success', $orderNumber, $order->customer_phone));
    }

    private function verifyDuePayment(EventPayment $payment, string $trxId): void
    {
        DB::transaction(function () use ($payment, $trxId): void {
            $payment->refresh();
            $order = $payment->order;

            if (! $order || $payment->payment_status === 'verified') {
                return;
            }

            $now = now();

            $payment->update([
                'payment_status' => 'verified',
                'transaction_reference' => $trxId,
                'paid_at' => $now,
                'verified_at' => $now,
            ]);
        });
    }

    private function merchantInvoiceFor(EventOrder $order, EventPaymentType $paymentType): string
    {
        $suffix = $paymentType === EventPaymentType::Due ? '-due' : '-adv';

        return 'ISF-'.$order->id.$suffix.'-'.Str::lower(Str::random(8));
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
