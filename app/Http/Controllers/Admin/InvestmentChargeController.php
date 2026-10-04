<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Ledger\Postings\InvestmentPostings;
use App\Models\CycleInvestment;
use App\Models\CycleInvestmentCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform service charge / asset rent levied on an event or business.
 */
class InvestmentChargeController extends Controller
{
    public function __construct(private readonly InvestmentPostings $postings) {}

    public function store(Request $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        $cycleInvestment->ensureActive();

        $attributes = $request->validate([
            'type' => ['required', 'string', Rule::in(CycleInvestmentCharge::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'charged_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $cycleInvestment, $attributes): void {
            $charge = $cycleInvestment->charges()->create([
                ...$attributes,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->postings->charge($charge, $request->user());
        });

        return back();
    }

    public function destroy(Request $request, CycleInvestment $cycleInvestment, CycleInvestmentCharge $charge): RedirectResponse
    {
        abort_unless($charge->cycle_investment_id === $cycleInvestment->id, 404);
        $cycleInvestment->ensureActive();

        DB::transaction(function () use ($request, $charge): void {
            $this->postings->removed($charge, $request->user());
            $charge->delete();
        });

        return back();
    }
}
