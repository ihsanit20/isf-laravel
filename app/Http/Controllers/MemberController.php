<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Members', [
            'members' => $user->managedMembers()
                ->with(['charges.category'])
                ->latest('applied_at')
                ->latest('id')
                ->get()
                ->map(fn(Member $member): array => $this->transformMember($member))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('members/Create', [
            'relationshipOptions' => Member::relationshipOptions(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->managedMembers()->create([
            ...$request->safe()->only([
                'full_name',
                'phone',
                'relationship_to_user',
                'units',
            ]),
            'status' => MemberStatus::Pending,
            'applied_at' => now(),
        ]);

        return to_route('members.index');
    }

    private function transformMember(Member $member): array
    {
        $registrationCharge = $member->charges->first(
            fn($charge) => $charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE,
        );

        return [
            'id' => $member->id,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'relationship_to_user' => $member->relationship_to_user,
            'units' => $member->units,
            'status' => $member->status->value,
            'rejection_note' => $member->rejection_note,
            'applied_at' => $member->applied_at?->format('d M Y, h:i A'),
            'approved_at' => $member->approved_at?->format('d M Y, h:i A'),
            'activated_at' => $member->activated_at?->format('d M Y, h:i A'),
            'registration_charge' => $registrationCharge ? [
                'amount' => $registrationCharge->amount,
                'status' => $registrationCharge->status,
            ] : null,
        ];
    }
}
