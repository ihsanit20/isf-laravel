<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'paymentMethods' => Member::registrationFeePaymentMethods(),
            'registrationFeeAmount' => Member::REGISTRATION_FEE_AMOUNT,
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $registrationFeeProofPath = $request->file('registration_fee_proof')?->store('member-registration-fees', 'public');

        $user->managedMembers()->create([
            ...$request->safe()->only([
                'full_name',
                'phone',
                'relationship_to_user',
                'units',
                'registration_fee_payment_method',
                'registration_fee_reference_no',
            ]),
            'registration_fee_amount' => Member::REGISTRATION_FEE_AMOUNT,
            'registration_fee_proof_path' => $registrationFeeProofPath,
            'status' => MemberStatus::Pending,
            'applied_at' => now(),
        ]);

        return to_route('members.index');
    }

    private function transformMember(Member $member): array
    {
        return [
            'id' => $member->id,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'relationship_to_user' => $member->relationship_to_user,
            'units' => $member->units,
            'registration_fee_amount' => $member->registration_fee_amount,
            'registration_fee_payment_method' => $member->registration_fee_payment_method,
            'registration_fee_payment_method_label' => Member::paymentMethodLabel($member->registration_fee_payment_method),
            'registration_fee_reference_no' => $member->registration_fee_reference_no,
            'registration_fee_proof_url' => $member->registration_fee_proof_path
                ? Storage::url($member->registration_fee_proof_path)
                : null,
            'status' => $member->status->value,
            'rejection_note' => $member->rejection_note,
            'applied_at' => $member->applied_at?->format('d M Y, h:i A'),
            'approved_at' => $member->approved_at?->format('d M Y, h:i A'),
        ];
    }
}
