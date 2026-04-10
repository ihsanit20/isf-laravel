<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundCycleRequest;
use App\Http\Requests\Admin\UpdateFundCycleRequest;
use App\Models\FundCycle;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FundCycleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/FundCycles', [
            'fundCycles' => FundCycle::query()
                ->with('creator:id,name')
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn(FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'start_date' => $fundCycle->start_date?->format('Y-m-d'),
                    'lock_date' => $fundCycle->lock_date?->format('Y-m-d'),
                    'maturity_date' => $fundCycle->maturity_date?->format('Y-m-d'),
                    'settlement_date' => $fundCycle->settlement_date?->format('Y-m-d'),
                    'notes' => $fundCycle->notes,
                    'created_by' => $fundCycle->creator?->name,
                    'created_at' => $fundCycle->created_at?->format('d M Y, h:i A'),
                ])
                ->values(),
            'statuses' => FundCycle::statuses(),
        ]);
    }

    public function store(StoreFundCycleRequest $request): RedirectResponse
    {
        FundCycle::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return to_route('admin.fund-cycles.index');
    }

    public function update(UpdateFundCycleRequest $request, FundCycle $fundCycle): RedirectResponse
    {
        $fundCycle->update($request->validated());

        return to_route('admin.fund-cycles.index');
    }
}
