<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventBankWithdrawalRequest;
use App\Http\Requests\Admin\UpdateEventBankWithdrawalRequest;
use App\Models\EventBankWithdrawal;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;

class EventBankWithdrawalController extends Controller
{
    public function store(StoreEventBankWithdrawalRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->bankWithdrawals()->create([
            ...$request->safe()->only(['withdrawal_date', 'amount', 'description', 'reference_no']),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'withdrawals',
        ]);
    }

    public function update(
        UpdateEventBankWithdrawalRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventBankWithdrawal $eventBankWithdrawal,
    ): RedirectResponse {
        $this->ensureWithdrawalBelongsToEvent($fundCycleEvent, $eventBankWithdrawal);

        $eventBankWithdrawal->update(
            $request->safe()->only(['withdrawal_date', 'amount', 'description', 'reference_no']),
        );

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'withdrawals',
        ]);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventBankWithdrawal $eventBankWithdrawal): RedirectResponse
    {
        $this->ensureWithdrawalBelongsToEvent($fundCycleEvent, $eventBankWithdrawal);

        $eventBankWithdrawal->delete();

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'withdrawals',
        ]);
    }

    private function ensureWithdrawalBelongsToEvent(
        FundCycleEvent $fundCycleEvent,
        EventBankWithdrawal $eventBankWithdrawal,
    ): void {
        if ($eventBankWithdrawal->fund_cycle_event_id !== $fundCycleEvent->id) {
            abort(404);
        }
    }
}
