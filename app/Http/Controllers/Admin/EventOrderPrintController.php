<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersPrintViews;
use App\Http\Controllers\Controller;
use App\Models\EventOrder;
use App\Models\EventPickupPoint;
use App\Models\FundCycleEvent;
use App\Services\EventOrderPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EventOrderPrintController extends Controller
{
    use RendersPrintViews;

    public function __construct(
        private readonly EventOrderPrintService $prints,
    ) {}

    public function pickupAll(Request $request, FundCycleEvent $fundCycleEvent): View|Response
    {
        $statusFilter = $this->prints->parseStatusFilter($request->query('status'));

        $data = $this->prints->pickupDispatchList($fundCycleEvent, null, $statusFilter);

        return $this->renderPrint(
            $request,
            'prints.event-orders.pickup-dispatch',
            $data,
            $this->pickupFilename($fundCycleEvent, 'all-hubs', $statusFilter),
        );
    }

    public function pickupHub(
        Request $request,
        FundCycleEvent $fundCycleEvent,
        EventPickupPoint $eventPickupPoint,
    ): View|Response {
        abort_unless($eventPickupPoint->fund_cycle_event_id === $fundCycleEvent->id, 404);

        $statusFilter = $this->prints->parseStatusFilter($request->query('status'));

        $data = $this->prints->pickupDispatchList($fundCycleEvent, $eventPickupPoint, $statusFilter);

        return $this->renderPrint(
            $request,
            'prints.event-orders.pickup-dispatch',
            $data,
            $this->pickupFilename($fundCycleEvent, $eventPickupPoint->name, $statusFilter),
        );
    }

    public function customerReceipt(
        Request $request,
        FundCycleEvent $fundCycleEvent,
        EventOrder $eventOrder,
    ): View|Response {
        abort_unless($eventOrder->fund_cycle_event_id === $fundCycleEvent->id, 404);

        $data = $this->prints->customerReceipt($eventOrder);

        return $this->renderPrint(
            $request,
            'prints.event-orders.customer-receipt',
            $data,
            'order-'.$eventOrder->order_number.'.pdf',
        );
    }

    public function packageSummary(Request $request, FundCycleEvent $fundCycleEvent): View|Response
    {
        $data = $this->prints->packagePackingSummary($fundCycleEvent);

        return $this->renderPrint(
            $request,
            'prints.event-orders.package-summary',
            $data,
            'packing-'.$fundCycleEvent->slug.'.pdf',
        );
    }

    private function pickupFilename(
        FundCycleEvent $event,
        string $hubLabel,
        string $statusFilter,
    ): string {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $hubLabel) ?: 'hub';

        return sprintf(
            'pickup-%s-%s-%s.pdf',
            $event->slug,
            strtolower($slug),
            $statusFilter,
        );
    }
}
