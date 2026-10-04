<?php

namespace App\Http\Controllers;

use App\Http\Requests\Members\StoreMemberFundCycleAllocationRequest;
use App\Ledger\Postings\MemberPostings;
use App\Models\FundCycle;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberFundCycleController extends Controller
{
    public function index(Request $request, Member $member): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($member->managed_by_user_id === $user->id, 404);

        return to_route('allocations.index', ['member' => $member->id]);
    }

    public function store(
        StoreMemberFundCycleAllocationRequest $request,
        Member $member,
        FundCycle $fundCycle,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless($member->managed_by_user_id === $user->id, 404);

        app(MemberPostings::class)->allocateToCycle([
            'fund_cycle_id' => $fundCycle->id,
            'member_id' => $member->id,
            'slot_key' => $request->string('slot_key')->trim()->toString(),
            'amount' => $fundCycle->allocationAmountFor($member->units),
            'allocated_at' => now(),
            'notes' => $request->validated('notes'),
            'created_by_user_id' => $user->id,
        ], $user->id);

        if ($request->string('return_to')->toString() === 'allocations') {
            return to_route('allocations.index', ['member' => $member->id]);
        }

        return to_route('members.fund-cycles.index', $member);
    }
}
