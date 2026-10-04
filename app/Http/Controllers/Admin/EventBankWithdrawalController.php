<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventBankWithdrawalRequest;
use App\Http\Requests\Admin\UpdateEventBankWithdrawalRequest;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventBankWithdrawal;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EventBankWithdrawalController extends Controller
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    public function store(StoreEventBankWithdrawalRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($request, $fundCycleEvent): void {
            $withdrawal = $fundCycleEvent->bankWithdrawals()->create([
                ...$request->safe()->only(['withdrawal_date', 'amount', 'description', 'reference_no']),
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->eventWithdrawal($withdrawal, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function update(
        UpdateEventBankWithdrawalRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventBankWithdrawal $eventBankWithdrawal,
    ): RedirectResponse {
        $this->ensureWithdrawalBelongsToEvent($fundCycleEvent, $eventBankWithdrawal);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($request, $eventBankWithdrawal): void {
            $eventBankWithdrawal->update(
                $request->safe()->only(['withdrawal_date', 'amount', 'description', 'reference_no']),
            );

            $this->postings->eventWithdrawal($eventBankWithdrawal, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventBankWithdrawal $eventBankWithdrawal): RedirectResponse
    {
        $this->ensureWithdrawalBelongsToEvent($fundCycleEvent, $eventBankWithdrawal);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($eventBankWithdrawal): void {
            $this->postings->removed($eventBankWithdrawal, request()->user());
            $eventBankWithdrawal->delete();
        });

        return $this->backToTab($fundCycleEvent);
    }

    private function backToTab(FundCycleEvent $fundCycleEvent): RedirectResponse
    {
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
