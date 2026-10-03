<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Postings\CyclePostings;
use App\Models\FundCycle;
use App\Models\FundCycleTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cycle-level income/expense and the final settlement of a fund cycle.
 */
class FundCycleLedgerController extends Controller
{
    public function __construct(private readonly CyclePostings $postings) {}

    public function storeTransaction(Request $request, FundCycle $fundCycle): RedirectResponse
    {
        abort_if($fundCycle->isSettled(), 403, 'This fund cycle has been settled and is locked for changes.');

        $attributes = $request->validate([
            'direction' => ['required', 'string', Rule::in(FundCycleTransaction::DIRECTIONS)],
            'category' => ['required', 'string', Rule::in(FundCycleTransaction::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $fundCycle, $attributes): void {
            $transaction = $fundCycle->transactions()->create([
                ...$attributes,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->transaction($transaction, $request->user());
        });

        return back();
    }

    public function destroyTransaction(Request $request, FundCycle $fundCycle, FundCycleTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->fund_cycle_id === $fundCycle->id, 404);

        DB::transaction(function () use ($request, $transaction): void {
            $this->postings->removed($transaction, $request->user());
            $transaction->delete();
        });

        return back();
    }

    public function settle(Request $request, FundCycle $fundCycle): RedirectResponse
    {
        $this->postings->settle($fundCycle, $request->user());

        return back();
    }
}
