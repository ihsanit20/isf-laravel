<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessSubEntryRequest;
use App\Http\Requests\Admin\UpdateBusinessSubEntryRequest;
use App\Models\BusinessSubEntry;
use App\Models\CycleInvestment;
use Illuminate\Http\RedirectResponse;

/**
 * Note-only operator records for a business. Nothing here touches the
 * journal, so entries stay editable even after the business is closed.
 */
class BusinessSubEntryController extends Controller
{
    public function store(StoreBusinessSubEntryRequest $request, CycleInvestment $cycleInvestment): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);

        $cycleInvestment->subEntries()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return back();
    }

    public function update(
        UpdateBusinessSubEntryRequest $request,
        CycleInvestment $cycleInvestment,
        BusinessSubEntry $subEntry,
    ): RedirectResponse {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);
        abort_unless($subEntry->cycle_investment_id === $cycleInvestment->id, 404);

        $subEntry->update($request->validated());

        return back();
    }

    public function destroy(CycleInvestment $cycleInvestment, BusinessSubEntry $subEntry): RedirectResponse
    {
        abort_unless($cycleInvestment->type === CycleInvestment::TYPE_BUSINESS, 404);
        abort_unless($subEntry->cycle_investment_id === $cycleInvestment->id, 404);

        $subEntry->delete();

        return back();
    }
}
