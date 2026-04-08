<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
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
                    'manager' => [
                        'name' => $member->manager?->name,
                        'email' => $member->manager?->email,
                    ],
                    'approver' => $member->approver?->name,
                ])
                ->values(),
        ]);
    }

    public function review(ReviewMemberRequest $request, Member $member): RedirectResponse
    {
        $status = MemberStatus::from($request->string('status')->toString());

        $data = [
            'status' => $status,
            'rejection_note' => $status === MemberStatus::Rejected
                ? $request->string('rejection_note')->toString()
                : null,
        ];

        if ($status === MemberStatus::Approved) {
            $data['approved_at'] = now();
            $data['approved_by_user_id'] = $request->user()?->id;
        } else {
            $data['approved_at'] = null;
            $data['approved_by_user_id'] = null;
        }

        $member->update($data);

        return to_route('admin.members.index');
    }
}
