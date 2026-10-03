<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventExpenseRequest;
use App\Http\Requests\Admin\UpdateEventExpenseRequest;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventExpense;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventExpenseController extends Controller
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    public function store(StoreEventExpenseRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->ensureNotFinalized();

        $receiptPath = $request->file('receipt')?->store(
            "event-expense-attachments/{$fundCycleEvent->id}",
            EventExpense::attachmentDisk(),
        );

        DB::transaction(function () use ($request, $fundCycleEvent, $receiptPath): void {
            $expense = $fundCycleEvent->expenses()->create([
                ...$request->safe()->only(['expense_date', 'category', 'paid_from', 'amount', 'description']),
                'receipt_path' => $receiptPath,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->eventExpense($expense, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function update(
        UpdateEventExpenseRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventExpense $eventExpense,
    ): RedirectResponse {
        $this->ensureExpenseBelongsToEvent($fundCycleEvent, $eventExpense);
        $fundCycleEvent->ensureNotFinalized();

        $attributes = $request->safe()->only(['expense_date', 'category', 'paid_from', 'amount', 'description']);

        if ($request->hasFile('receipt')) {
            if ($eventExpense->receipt_path !== null) {
                Storage::disk(EventExpense::attachmentDisk())->delete($eventExpense->receipt_path);
            }

            $attributes['receipt_path'] = $request->file('receipt')?->store(
                "event-expense-attachments/{$fundCycleEvent->id}",
                EventExpense::attachmentDisk(),
            );
        }

        DB::transaction(function () use ($request, $eventExpense, $attributes): void {
            $eventExpense->update($attributes);
            $this->postings->eventExpense($eventExpense, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventExpense $eventExpense): RedirectResponse
    {
        $this->ensureExpenseBelongsToEvent($fundCycleEvent, $eventExpense);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($eventExpense): void {
            $this->postings->removed($eventExpense, request()->user());
            $eventExpense->delete();
        });

        if ($eventExpense->receipt_path !== null) {
            Storage::disk(EventExpense::attachmentDisk())->delete($eventExpense->receipt_path);
        }

        return $this->backToTab($fundCycleEvent);
    }

    private function backToTab(FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'costs',
        ]);
    }

    private function ensureExpenseBelongsToEvent(FundCycleEvent $fundCycleEvent, EventExpense $eventExpense): void
    {
        if ($eventExpense->fund_cycle_event_id !== $fundCycleEvent->id) {
            abort(404);
        }
    }
}
