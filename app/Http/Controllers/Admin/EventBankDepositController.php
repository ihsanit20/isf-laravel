<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventBankDepositRequest;
use App\Http\Requests\Admin\UpdateEventBankDepositRequest;
use App\Models\EventBankDeposit;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;

class EventBankDepositController extends Controller
{
    public function store(StoreEventBankDepositRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->bankDeposits()->create([
            ...$request->safe()->only(['deposit_date', 'amount', 'description', 'reference_no']),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'deposits',
        ]);
    }

    public function update(
        UpdateEventBankDepositRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventBankDeposit $eventBankDeposit,
    ): RedirectResponse {
        $this->ensureDepositBelongsToEvent($fundCycleEvent, $eventBankDeposit);

        $eventBankDeposit->update(
            $request->safe()->only(['deposit_date', 'amount', 'description', 'reference_no']),
        );

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'deposits',
        ]);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventBankDeposit $eventBankDeposit): RedirectResponse
    {
        $this->ensureDepositBelongsToEvent($fundCycleEvent, $eventBankDeposit);

        $eventBankDeposit->delete();

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'deposits',
        ]);
    }

    private function ensureDepositBelongsToEvent(
        FundCycleEvent $fundCycleEvent,
        EventBankDeposit $eventBankDeposit,
    ): void {
        if ($eventBankDeposit->fund_cycle_event_id !== $fundCycleEvent->id) {
            abort(404);
        }
    }
}
