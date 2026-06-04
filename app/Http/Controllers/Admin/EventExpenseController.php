<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventExpenseRequest;
use App\Http\Requests\Admin\UpdateEventExpenseRequest;
use App\Models\EventExpense;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class EventExpenseController extends Controller
{
    public function store(StoreEventExpenseRequest $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $receiptPath = $request->file('receipt')?->store(
            "event-expense-attachments/{$fundCycleEvent->id}",
            EventExpense::attachmentDisk(),
        );

        $fundCycleEvent->expenses()->create([
            ...$request->safe()->only(['expense_date', 'category', 'amount', 'description']),
            'receipt_path' => $receiptPath,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'costs',
        ]);
    }

    public function update(
        UpdateEventExpenseRequest $request,
        FundCycleEvent $fundCycleEvent,
        EventExpense $eventExpense,
    ): RedirectResponse {
        $this->ensureExpenseBelongsToEvent($fundCycleEvent, $eventExpense);

        $attributes = $request->safe()->only(['expense_date', 'category', 'amount', 'description']);

        if ($request->hasFile('receipt')) {
            if ($eventExpense->receipt_path !== null) {
                Storage::disk(EventExpense::attachmentDisk())->delete($eventExpense->receipt_path);
            }

            $attributes['receipt_path'] = $request->file('receipt')?->store(
                "event-expense-attachments/{$fundCycleEvent->id}",
                EventExpense::attachmentDisk(),
            );
        }

        $eventExpense->update($attributes);

        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'costs',
        ]);
    }

    public function destroy(FundCycleEvent $fundCycleEvent, EventExpense $eventExpense): RedirectResponse
    {
        $this->ensureExpenseBelongsToEvent($fundCycleEvent, $eventExpense);

        if ($eventExpense->receipt_path !== null) {
            Storage::disk(EventExpense::attachmentDisk())->delete($eventExpense->receipt_path);
        }

        $eventExpense->delete();

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
