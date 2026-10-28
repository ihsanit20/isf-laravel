<?php

namespace App\Services;

use App\Enums\EventOrderStatus;
use App\Enums\EventPaymentType;
use App\Enums\FundCycleEventStatus;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventOrder;
use App\Models\EventPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Msilabs\Bkash\BkashResponse;
use Msilabs\Bkash\Exceptions\BkashException;
use Msilabs\Bkash\Facades\Bkash;
use Symfony\Component\HttpFoundation\Response;

class EventBkashPaymentService
{
    public function __construct(
        private readonly EventOrderConfirmationService $orderConfirmation,
        private readonly InvestmentPostings $investmentPostings,
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
        if (! $order->canAcceptDuePayment()) {
            throw new \InvalidArgumentException('Due payment is not available for this order.');
        }

        if ($order->hasBlockingPendingPayment()) {
            throw new \InvalidArgumentException('A payment is already pending for this order.');
        }

        $order->supersedePendingBkashDuePayments();

        return $this->createBkashPayment($order, $order->dueAmount(), EventPaymentType::Due);
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
        $paymentId = $request->input('paymentId', $request->input('paymentID'));
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

        // bKash takes the money only on execute, so refuse here rather than
        // record money the event can no longer account for.
        if (($refusal = $this->executeRefusal($payment, $order)) !== null) {
            $this->markPaymentFailed($payment, $refusal);

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $response = $this->executeOrRecover($paymentId);

        if ($response === null) {
            // bKash may have taken the money: keep it pending so a later
            // callback or queryPayment can still verify it.
            $this->notePendingPayment($payment, 'bKash execute result unknown; verify via queryPayment.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        if (! $response->isCompleted()) {
            $this->markPaymentFailed(
                $payment,
                $response->errorMessage() ?? 'Transaction status: '.($response->transactionStatus ?? 'unknown'),
            );

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        // Execute returns merchantInvoiceNumber, query returns merchantInvoice.
        $invoice = (string) ($response->merchantInvoiceNumber ?? $response->merchantInvoice ?? '');
        if ($invoice !== $payment->merchant_invoice) {
            $this->markPaymentFailed($payment, 'Merchant invoice mismatch.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $paidAmount = (float) ($response->amount ?? 0);
        if (round($paidAmount, 2) !== round((float) $payment->amount, 2)) {
            $this->markPaymentFailed($payment, 'Paid amount does not match expected amount.');

            return redirect($this->frontendRedirect('failed', $orderNumber, $order->customer_phone));
        }

        $trxId = (string) ($response->trxId ?? '');

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

        $response = Bkash::createPayment(
            $amount,
            $merchantInvoice,
            $this->callbackUrl(),
            $order->customer_phone,
        )->throw();

        if (! $response->paymentId || ! $response->redirectUrl()) {
            throw new BkashException('bKash create payment returned no payment ID or URL.');
        }

        EventPayment::query()->create([
            'event_order_id' => $order->id,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'payment_method' => 'bkash',
            'payment_status' => 'pending',
            'bkash_payment_id' => $response->paymentId,
            'merchant_invoice' => $merchantInvoice,
        ]);

        return [
            'redirect_url' => $response->redirectUrl(),
        ];
    }

    /**
     * Execute the payment, falling back to a status query when execute fails
     * or errors out. Null means bKash's result is unknown.
     */
    private function executeOrRecover(string $paymentId): ?BkashResponse
    {
        try {
            $response = Bkash::executePayment($paymentId);

            if ($response->isCompleted()) {
                return $response;
            }
        } catch (BkashException) {
            $response = null;
        }

        try {
            $query = Bkash::queryPayment($paymentId);
        } catch (BkashException) {
            // An execute error such as "already executed" may still mean paid.
            return null;
        }

        return $query->isCompleted() ? $query : ($response ?? $query);
    }

    private function completeAdvanceCallback(
        EventOrder $order,
        EventPayment $payment,
        string $trxId,
        string $orderNumber,
    ): Response {
        $confirmed = false;

        DB::transaction(function () use ($order, $payment, $trxId, &$confirmed): void {
            $payment = EventPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $order->refresh();

            // bKash has already taken the money: always record it, even when the
            // order was confirmed or cancelled meanwhile (refund handles the latter).
            if ($payment->payment_status !== 'verified') {
                $now = now();

                $payment->update([
                    'payment_status' => 'verified',
                    'transaction_reference' => $trxId,
                    'paid_at' => $now,
                    'verified_at' => $now,
                ]);

                $this->investmentPostings->customerPaymentVerified($payment);
            }

            if ($order->status !== EventOrderStatus::Pending) {
                return;
            }

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
            $payment = EventPayment::query()->lockForUpdate()->findOrFail($payment->id);
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

            $this->investmentPostings->customerPaymentVerified($payment);
        });
    }

    private function merchantInvoiceFor(EventOrder $order, EventPaymentType $paymentType): string
    {
        $suffix = $paymentType === EventPaymentType::Due ? '-due' : '-adv';

        return 'ISF-'.$order->id.$suffix.'-'.Str::lower(Str::random(8));
    }

    /**
     * Why this payment must not be executed, or null when it may.
     */
    private function executeRefusal(EventPayment $payment, EventOrder $order): ?string
    {
        if ($payment->payment_status !== 'pending') {
            return 'Payment attempt was already expired or superseded.';
        }

        $event = $order->fundCycleEvent;

        if ($event === null || $event->is_finalized || $event->status === FundCycleEventStatus::Cancelled) {
            return 'The event is finalized or cancelled.';
        }

        if ($payment->payment_type === EventPaymentType::Due && (float) $payment->amount > $order->dueAmount()) {
            return 'Payment exceeds the order due balance.';
        }

        return null;
    }

    private function markPaymentFailed(EventPayment $payment, string $note): void
    {
        // Re-read under lock: a parallel callback may have verified it meanwhile.
        DB::transaction(function () use ($payment, $note): void {
            $payment = EventPayment::query()->lockForUpdate()->find($payment->id);

            if ($payment === null || $payment->payment_status === 'verified') {
                return;
            }

            $payment->update([
                'payment_status' => 'failed',
                'note' => Str::limit($note, 500),
            ]);
        });
    }

    private function notePendingPayment(EventPayment $payment, string $note): void
    {
        EventPayment::query()
            ->whereKey($payment->id)
            ->where('payment_status', 'pending')
            ->update(['note' => Str::limit($note, 500)]);
    }
}
