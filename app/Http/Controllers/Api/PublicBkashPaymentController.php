<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use App\Services\EventBkashPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBkashPaymentController extends Controller
{
    public function __construct(
        private readonly EventBkashPaymentService $bkashPayments,
    ) {}

    public function init(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'customer_phone' => ['required', 'string', 'max:20'],
        ]);

        $order = EventOrder::query()
            ->where('order_number', $orderNumber)
            ->where('customer_phone', $validated['customer_phone'])
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'No order found with the given order number and phone.',
            ], 404);
        }

        if ($order->status === EventOrderStatus::Confirmed) {
            return response()->json([
                'message' => 'This order is already confirmed.',
            ], 422);
        }

        if ((float) $order->advance_amount <= 0) {
            return response()->json([
                'message' => 'No advance payment is required for this order.',
            ], 422);
        }

        try {
            $result = $this->bkashPayments->initiateAdvance($order);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to start bKash payment. Please try again later.',
            ], 502);
        }

        return response()->json([
            'message' => 'bKash payment initiated.',
            'data' => $result,
        ]);
    }

    public function initDue(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'customer_phone' => ['required', 'string', 'max:20'],
        ]);

        $order = EventOrder::query()
            ->where('order_number', $orderNumber)
            ->where('customer_phone', $validated['customer_phone'])
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'No order found with the given order number and phone.',
            ], 404);
        }

        if (! $order->canAcceptDuePayment()) {
            return response()->json([
                'message' => 'Due payment is not available for this order.',
            ], 422);
        }

        try {
            $result = $this->bkashPayments->initiateDue($order);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to start bKash payment. Please try again later.',
            ], 502);
        }

        return response()->json([
            'message' => 'bKash due payment initiated.',
            'data' => $result,
        ]);
    }
}
