<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return Inertia::render('Dashboard', [
                'roleLabel' => $user->role === UserRole::SUPER_ADMIN ? 'Super Admin' : 'Admin',
                'stats' => [
                    [
                        'label' => 'Total members',
                        'value' => Member::query()->count(),
                        'detail' => 'All member profiles currently tracked in ISF.',
                    ],
                    [
                        'label' => 'Active members',
                        'value' => Member::query()->where('status', Member::STATUS_ACTIVE)->count(),
                        'detail' => 'Member profiles that can receive savings activity.',
                    ],
                    [
                        'label' => 'Registered users',
                        'value' => User::query()->count(),
                        'detail' => 'Login accounts across admin and member roles.',
                    ],
                ],
                'recentMembers' => Member::query()
                    ->with('user:id,name')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn(Member $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'member_code' => $member->member_code,
                        'status' => $member->status,
                        'user_name' => $member->user?->name,
                    ]),
                'linkedMembers' => [],
            ]);
        }

        return Inertia::render('Dashboard', [
            'roleLabel' => 'Member',
            'stats' => [
                [
                    'label' => 'Linked profiles',
                    'value' => $user->members()->count(),
                    'detail' => 'Member memberships assigned to this login account.',
                ],
                [
                    'label' => 'Active profiles',
                    'value' => $user->members()->where('status', Member::STATUS_ACTIVE)->count(),
                    'detail' => 'Profiles currently active in the savings fund.',
                ],
                [
                    'label' => 'Pending setup',
                    'value' => $user->members()->exists() ? 0 : 1,
                    'detail' => 'If this is 1, an admin still needs to assign your member profile.',
                ],
            ],
            'linkedMembers' => $user->members()
                ->latest()
                ->get()
                ->map(fn(Member $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'member_code' => $member->member_code,
                    'status' => $member->status,
                    'joined_at' => $member->joined_at?->toFormattedDateString(),
                ]),
            'recentMembers' => [],
        ]);
    }
}
