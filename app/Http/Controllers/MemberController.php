<?php

namespace App\Http\Controllers;

use App\Http\Requests\Members\StoreMemberRequest;
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
            'relationshipOptions' => Member::relationshipOptions(),
            'members' => $user->managedMembers()
                ->latest('applied_at')
                ->latest('id')
                ->get()
                ->map(fn(Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'phone' => $member->phone,
                    'relationship_to_user' => $member->relationship_to_user,
                    'units' => $member->units,
                    'status' => $member->status,
                    'rejection_note' => $member->rejection_note,
                    'applied_at' => $member->applied_at?->format('d M Y, h:i A'),
                    'approved_at' => $member->approved_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->managedMembers()->create([
            ...$request->safe()->only(['full_name', 'phone', 'relationship_to_user', 'units']),
            'status' => Member::STATUS_PENDING,
            'applied_at' => now(),
        ]);

        return to_route('members.index');
    }
}
