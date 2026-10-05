<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        /** @var User $user */
        $user = $request->user();

        $capitalByMember = $ledger->balancesBy('member_id', Account::CycleCapital, ['user_id' => $user->id])
            ->map(fn (int $balance): int => -$balance);

        return Inertia::render('Members', [
            'allocationSummary' => [
                'available_to_allocate' => (int) Money::toTaka(app(MemberPostings::class)->availableBalance($user->id)),
            ],
            'members' => $user->managedMembers()
                ->with(['charges.category', 'charges.allocations'])
                ->orderBy('id')
                ->get()
                ->map(fn (Member $member): array => $this->transformMember($member, $capitalByMember->get($member->id, 0)))
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

    private function transformMember(Member $member, int $capitalInCycles): array
    {
        $registrationCharge = $member->charges->first(
            fn ($charge) => $charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE,
        );
        $registrationChargePaidAt = $registrationCharge?->allocations
            ->whereNull('reversed_at')
            ->sortByDesc('confirmed_at')
            ->first()?->confirmed_at?->format('d M Y, h:i A');

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
                'id' => $registrationCharge->id,
                'amount' => $registrationCharge->amount,
                'status' => $registrationCharge->status,
                'paid_at' => $registrationChargePaidAt,
            ] : null,
            'capital_in_cycles' => Money::toTaka($capitalInCycles),
            'charges' => $member->charges
                ->sortByDesc('effective_at')
                ->map(fn (Charge $charge): array => [
                    'id' => $charge->id,
                    'title' => $charge->category?->title,
                    'code' => $charge->category?->code,
                    'amount' => $charge->amount,
                    'status' => $charge->status,
                    'effective_at' => $charge->effective_at?->format('d M Y'),
                    'paid_at' => $charge->allocations
                        ->whereNull('reversed_at')
                        ->sortByDesc('confirmed_at')
                        ->first()?->confirmed_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ];
    }
}
