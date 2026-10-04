<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\EventIncome;
use App\Models\FundCycleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventIncomeController extends Controller
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    public function store(Request $request, FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        $fundCycleEvent->ensureNotFinalized();
        $attributes = $this->validated($request);

        DB::transaction(function () use ($request, $fundCycleEvent, $attributes): void {
            $income = $fundCycleEvent->incomes()->create([
                ...$attributes,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->eventIncome($income, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function update(Request $request, FundCycleEvent $fundCycleEvent, EventIncome $eventIncome): RedirectResponse
    {
        abort_unless($eventIncome->fund_cycle_event_id === $fundCycleEvent->id, 404);
        $fundCycleEvent->ensureNotFinalized();
        $attributes = $this->validated($request);

        DB::transaction(function () use ($request, $eventIncome, $attributes): void {
            $eventIncome->update($attributes);
            $this->postings->eventIncome($eventIncome, $request->user());
        });

        return $this->backToTab($fundCycleEvent);
    }

    public function destroy(Request $request, FundCycleEvent $fundCycleEvent, EventIncome $eventIncome): RedirectResponse
    {
        abort_unless($eventIncome->fund_cycle_event_id === $fundCycleEvent->id, 404);
        $fundCycleEvent->ensureNotFinalized();

        DB::transaction(function () use ($request, $eventIncome): void {
            $this->postings->removed($eventIncome, $request->user());
            $eventIncome->delete();
        });

        return $this->backToTab($fundCycleEvent);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'income_date' => ['required', 'date'],
            'category' => ['required', 'string', Rule::in(EventIncome::CATEGORIES)],
            'received_via' => ['required', 'string', Rule::in(['cash', 'bkash', 'bank'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function backToTab(FundCycleEvent $fundCycleEvent): RedirectResponse
    {
        return to_route('admin.events.show', [
            'fundCycleEvent' => $fundCycleEvent,
            'tab' => 'accounts',
        ]);
    }
}
