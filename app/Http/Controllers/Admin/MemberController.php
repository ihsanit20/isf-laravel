<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Members/Index', [
            'members' => Member::query()
                ->with('user:id,name,email,role')
                ->orderBy('name')
                ->get()
                ->map(fn(Member $member) => [
                    'id' => $member->id,
                    'member_code' => $member->member_code,
                    'name' => $member->name,
                    'phone' => $member->phone,
                    'status' => $member->status,
                    'joined_at' => $member->joined_at?->format('Y-m-d'),
                    'user_id' => $member->user_id,
                    'user_name' => $member->user?->name,
                    'user_email' => $member->user?->email,
                ]),
            'eligibleUsers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role'])
                ->map(fn(User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                ]),
            'memberStatuses' => Member::statuses(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Member::create([
            'user_id' => $validated['user_id'] ?? null,
            'member_code' => $this->generateMemberCode(),
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'joined_at' => $validated['joined_at'],
        ]);

        return redirect()
            ->route('admin.members.index')
            ->with('success', 'Member profile created successfully.');
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $validated = $request->validated();

        $member->update([
            'user_id' => $validated['user_id'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.members.index')
            ->with('success', 'Member profile updated successfully.');
    }

    private function generateMemberCode(): string
    {
        do {
            $code = 'ISF-' . Str::upper(Str::random(8));
        } while (Member::query()->where('member_code', $code)->exists());

        return $code;
    }
}
