<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\DepositSubmission;
use App\Models\FundCycleAllocation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserListController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $verifiedDepositTotals = DepositSubmission::query()
            ->where('status', DepositSubmissionStatus::Verified)
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $memberAllocatedTotals = FundCycleAllocation::query()
            ->join('members', 'members.id', '=', 'fund_cycle_allocations.member_id')
            ->selectRaw('members.managed_by_user_id as user_id, SUM(fund_cycle_allocations.amount) as total')
            ->groupBy('members.managed_by_user_id')
            ->pluck('total', 'user_id');

        return Inertia::render('admin/Users', [
            'assignableRoles' => User::assignableRolesFor($actor->role),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role'])
                ->map(fn(User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'can_edit' => $actor->canManageUser($user),
                    'total_verified_deposit_amount' => (int) ($verifiedDepositTotals[$user->id] ?? 0),
                    'member_total_allocated_amount' => (int) ($memberAllocatedTotals[$user->id] ?? 0),
                ])
                ->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->safe()->only(['name', 'email', 'phone', 'role', 'password']));

        return to_route('admin.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'phone', 'role']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->update($data);

        return to_route('admin.users.index');
    }
}
