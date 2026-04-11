<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewMemberRequest;
use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\Member;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberListController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Members', [
            'members' => Member::query()
                ->with(['manager:id,name,email', 'approver:id,name'])
                ->latest('applied_at')
                ->latest('id')
                ->get()
                ->map(fn(Member $member): array => [
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
                    'manager' => [
                        'name' => $member->manager?->name,
                        'email' => $member->manager?->email,
                    ],
                    'approver' => $member->approver?->name,
                ])
                ->values(),
        ]);
    }

    public function review(ReviewMemberRequest $request, Member $member, SmsService $smsService): RedirectResponse
    {
        $status = MemberStatus::from($request->string('status')->toString());
        $wasApproved = $member->status === MemberStatus::Approved;

        $data = [
            'status' => $status,
            'rejection_note' => $status === MemberStatus::Rejected
                ? $request->string('rejection_note')->toString()
                : null,
        ];

        if ($status === MemberStatus::Approved) {
            $data['approved_at'] = now();
            $data['approved_by_user_id'] = $request->user()?->id;
            $data['activated_at'] = $wasApproved ? $member->activated_at : null;
        } elseif ($status === MemberStatus::Exited && $wasApproved) {
            $data['approved_at'] = $member->approved_at;
            $data['approved_by_user_id'] = $member->approved_by_user_id;
            $data['activated_at'] = null;
        } else {
            $data['approved_at'] = null;
            $data['activated_at'] = null;
            $data['approved_by_user_id'] = null;
        }

        DB::transaction(function () use ($member, $data, $status, $wasApproved): void {
            $member->update($data);

            if ($status === MemberStatus::Approved && ! $wasApproved) {
                $category = ChargeCategory::query()
                    ->where('code', ChargeCategory::CODE_REGISTRATION_FEE)
                    ->where('is_active', true)
                    ->firstOrFail();

                Charge::query()->firstOrCreate(
                    [
                        'charge_category_id' => $category->id,
                        'member_id' => $member->id,
                        'status' => Charge::STATUS_PENDING,
                    ],
                    [
                        'amount' => $category->default_amount,
                        'effective_at' => now(),
                    ],
                );
            }
        });

        if ($status === MemberStatus::Approved) {
            $smsService->send(
                (string) ($member->manager?->phone ?? $member->phone ?? ''),
                sprintf(
                    'ISF member approved. Member: %s. Units: %d.',
                    $member->full_name,
                    $member->units,
                ),
                $member,
            );
        }

        return to_route('admin.members.index');
    }
}
