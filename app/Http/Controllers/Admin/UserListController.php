<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Ledger\Account;
use App\Ledger\Ledger;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class UserListController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $verifiedDepositTotals = $this->depositTotalsByUser();
        $memberAllocatedTotals = app(MemberPostings::class)->allocatedCapitalBy('user_id');
        $availableBalances = app(Ledger::class)->balancesBy('user_id', Account::MemberBalance);

        return Inertia::render('admin/Users', [
            'assignableRoles' => User::assignableRolesFor($actor->role),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'can_edit' => $actor->canManageUser($user),
                    'total_verified_deposit_amount' => Money::toTaka($verifiedDepositTotals->get($user->id, 0)),
                    'member_total_allocated_amount' => Money::toTaka($memberAllocatedTotals->get($user->id, 0)),
                    'available_balance' => Money::toTaka(-$availableBalances->get($user->id, 0)),
                ])
                ->values(),
        ]);
    }

    /**
     * Verified deposits per user, in paisa, from the journal.
     *
     * @return Collection<int|string, int>
     */
    private function depositTotalsByUser(): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.ledger_account_id', LedgerAccount::idFor(Account::MemberBalance))
            ->where('journal_entries.kind', 'like', 'deposit_verified%')
            ->selectRaw('journal_lines.user_id as user_id')
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as total')
            ->groupBy('journal_lines.user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($total): int => (int) $total);
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
