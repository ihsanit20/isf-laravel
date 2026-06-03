<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPrintViews;
use App\Models\EventOrder;
use App\Models\EventPayment;
use App\Services\EventOrderPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicPaymentReceiptController extends Controller
{
    use RendersPrintViews;

    public function __construct(
        private readonly EventOrderPrintService $prints,
    ) {}

    public function show(Request $request, string $orderNumber, EventPayment $payment): View|Response
    {
        $order = EventOrder::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        abort_unless($payment->event_order_id === $order->id, 404);
        abort_unless($payment->payment_status === 'verified', 404);
        abort_unless($this->canAccessOrder($request, $order), 404);

        $data = $this->prints->paymentReceipt($order, $payment);

        return $this->renderPrint(
            $request,
            'prints.event-orders.payment-receipt',
            $data,
            sprintf('payment-%s-%d.pdf', $order->order_number, $payment->id),
        );
    }

    private function canAccessOrder(Request $request, EventOrder $order): bool
    {
        $token = $request->query('token');
        if (is_string($token) && $token !== '' && hash_equals((string) $order->tracking_token, $token)) {
            return true;
        }

        $phone = $request->query('customer_phone');
        if (is_string($phone) && $phone !== '' && $order->customer_phone === $phone) {
            return true;
        }

        return false;
    }
}
