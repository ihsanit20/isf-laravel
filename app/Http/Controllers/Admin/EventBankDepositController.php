<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventBankDepositRequest;
use App\Http\Requests\Admin\UpdateEventBankDepositRequest;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventBankDeposit;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EventBankDepositController extends Controller
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    public function store(StoreEventBankDepositRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($request, $fundCycleEvent): void {
            $deposit = $fundCycleEvent->bankDeposits()->create([
                ...$request->safe()->only(['deposit_date', 'amount', 'source', 'description', 'reference_no']),
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->eventBankDeposit($deposit, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function update(
        UpdateEventBankDepositRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventBankDeposit $eventBankDeposit,
    ): RedirectResponse {
        $this->ensureDepositBelongsToEvent($fundCycleEvent, $eventBankDeposit);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($request, $eventBankDeposit): void {
            $eventBankDeposit->update(
                $request->safe()->only(['deposit_date', 'amount', 'source', 'description', 'reference_no']),
            );

            $this->postings->eventBankDeposit($eventBankDeposit, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventBankDeposit $eventBankDeposit): RedirectResponse
    {
        $this->ensureDepositBelongsToEvent($fundCycleEvent, $eventBankDeposit);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($eventBankDeposit): void {
            $this->postings->removed($eventBankDeposit, request()->user());
            $eventBankDeposit->delete();
        });

        return $this->backToTab($fundCycleEvent);
    }

    private function backToTab(FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'deposits',
        ]);
    }

    private function ensureDepositBelongsToEvent(FundCycleEvent $fundCycleEvent, EventBankDeposit $eventBankDeposit): void
    {
        if ($eventBankDeposit->fund_cycle_event_id !== $fundCycleEvent->id) {
            abort(404);
        }
    }
}
