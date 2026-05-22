<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventPackageRequest;
use App\Http\Requests\Admin\UpdateEventPackageRequest;
use App\Models\EventPackage;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;

class EventPackageController extends Controller
{
    public function store(StoreEventPackageRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->packages()->create($request->validated());

        return to_route('admin.events.show', $fundCycleEvent);
    }

    public function update(
        UpdateEventPackageRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventPackage $eventPackage,
    ): RedirectResponse {
        $eventPackage->update($request->validated());

        return to_route('admin.events.show', $fundCycleEvent);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventPackage $eventPackage): RedirectResponse
    {
        $eventPackage->delete();

        return to_route('admin.events.show', $fundCycleEvent);
    }
}
