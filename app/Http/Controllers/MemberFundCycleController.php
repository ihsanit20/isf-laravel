<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\FundCycle;
use App\Models\FundCycleAllocation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberFundCycleController extends Controller
{
    public function index(Request $request, Member $member): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($member->managed_by_user_id === $user->id, 404);

        return Inertia::render('members/FundCycles', [
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'status' => $member->status->value,
                'approved_at' => $member->approved_at?->format('d M Y, h:i A'),
                'activated_at' => $member->activated_at?->format('d M Y, h:i A'),
            ],
            'fundCycles' => FundCycle::query()
                ->withCount('allocations')
                ->where('status', FundCycle::STATUS_OPEN)
                ->latest('start_date')
                ->latest('id')
                ->get()
                ->map(fn(FundCycle $fundCycle): array => [
                    'id' => $fundCycle->id,
                    'name' => $fundCycle->name,
                    'status' => $fundCycle->status,
                    'status_label' => FundCycle::statusLabel($fundCycle->status),
                    'start_date' => $fundCycle->start_date?->format('d M Y'),
                    'lock_date' => $fundCycle->lock_date?->format('d M Y'),
                    'maturity_date' => $fundCycle->maturity_date?->format('d M Y'),
                    'settlement_date' => $fundCycle->settlement_date?->format('d M Y'),
                    'slots' => collect($fundCycle->slots ?? [])->values(),
                    'allocations_count' => $fundCycle->allocations_count,
                    'has_member_allocation' => FundCycleAllocation::query()
                        ->where('fund_cycle_id', $fundCycle->id)
                        ->where('member_id', $member->id)
                        ->exists(),
                ])
                ->values(),
        ]);
    }
}
