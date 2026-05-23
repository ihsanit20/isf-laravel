<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventPickupPointRequest;
use App\Http\Requests\Admin\UpdateEventPickupPointRequest;
use App\Models\EventPickupPoint;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;

class EventPickupPointController extends Controller
{
    public function store(StoreEventPickupPointRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->pickupPoints()->create($request->validated());

        return to_route('admin.events.show', $fundCycleEvent);
    }

    public function update(
        UpdateEventPickupPointRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventPickupPoint $eventPickupPoint,
    ): RedirectResponse {
        $eventPickupPoint->update($request->validated());

        return to_route('admin.events.show', $fundCycleEvent);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventPickupPoint $eventPickupPoint): RedirectResponse
    {
        $eventPickupPoint->delete();

        return to_route('admin.events.show', $fundCycleEvent);
    }
}
