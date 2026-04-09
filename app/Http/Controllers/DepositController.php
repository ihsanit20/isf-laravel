<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Requests\Deposits\StoreDepositAllocationRequest;
use App\Http\Requests\Deposits\StoreDepositSubmissionRequest;
use App\Models\DepositAllocation;
use App\Models\DepositSubmission;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deposits = $user->depositSubmissions()
            ->latest('deposit_date')
            ->latest('id')
            ->get();

        $allocations = $this->managedAllocationsQuery($user)
            ->with('member:id,full_name')
            ->newestFirst()
            ->get();

        $summary = $this->buildDepositSummary($deposits, $allocations, $user->managedMembers()->where('status', MemberStatus::Approved)->exists());

        return Inertia::render('Deposits', [
            'summary' => $summary,
            'deposits' => $deposits
                ->map(fn(DepositSubmission $depositSubmission): array => $this->transformDeposit($depositSubmission))
                ->values(),
            'allocations' => $allocations
                ->map(fn(DepositAllocation $allocation): array => $this->transformAllocation($allocation))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('deposits/Create', [
            'paymentMethods' => DepositSubmission::paymentMethods(),
        ]);
    }

    public function store(StoreDepositSubmissionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $proofPath = $request->file('proof')?->store('deposit-proofs', 'public');

        $user->depositSubmissions()->create([
            ...$request->safe()->only(['amount', 'payment_method', 'reference_no', 'deposit_date', 'notes']),
            'proof_path' => $proofPath,
            'status' => DepositSubmissionStatus::Pending,
        ]);

        return to_route('deposits.index');
    }

    public function allocate(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deposits = $user->depositSubmissions()->get();
        $allocations = $this->managedAllocationsQuery($user)->get();

        return Inertia::render('deposits/Allocate', [
            'summary' => $this->buildDepositSummary($deposits, $allocations, true),
            'members' => Member::query()
                ->where('managed_by_user_id', $user->id)
                ->where('status', MemberStatus::Approved)
                ->latest('approved_at')
                ->latest('id')
                ->get()
                ->map(fn(Member $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'units' => $member->units,
                    'monthly_due_amount' => $member->units * DepositAllocation::DEFAULT_UNIT_AMOUNT,
                ])
                ->values(),
        ]);
    }

    public function storeAllocations(StoreDepositAllocationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $verifiedDeposits = DepositSubmission::query()
                ->where('user_id', $user->id)
                ->where('status', DepositSubmissionStatus::Verified)
                ->lockForUpdate()
                ->get();

            $existingAllocations = $this->managedAllocationsQuery($user)
                ->lockForUpdate()
                ->get();

            if ($verifiedDeposits->isEmpty()) {
                throw ValidationException::withMessages([
                    'rows' => 'No verified deposits are available for allocation.',
                ]);
            }

            $rows = collect($request->validated('rows'));
            $totalAllocatedAmount = $rows->sum(
                fn(array $row): int => (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT,
            );

            $totalAllocatableAmount = $this->allocatableAmountFromTotals(
                (int) $verifiedDeposits->sum('amount'),
                (int) $existingAllocations->sum('allocated_amount'),
            );

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                throw ValidationException::withMessages([
                    'rows' => 'Allocated amount cannot exceed the total allocatable verified deposit amount.',
                ]);
            }

            foreach ($rows as $row) {
                DepositAllocation::query()->create([
                    'member_id' => (int) $row['member_id'],
                    'allocation_month' => CarbonImmutable::createFromFormat('Y-m', $row['allocation_month'])
                        ->startOfMonth()
                        ->toDateString(),
                    'units' => (int) $row['units'],
                    'unit_amount' => DepositAllocation::DEFAULT_UNIT_AMOUNT,
                    'allocated_amount' => (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT,
                    'confirmed_at' => now(),
                ]);
            }
        });

        return to_route('deposits.index');
    }

    private function buildDepositSummary(Collection $deposits, Collection $allocations, bool $hasApprovedMembers): array
    {
        $totalDepositAmount = (int) $deposits->sum('amount');
        $totalVerifiedAmount = (int) $deposits
            ->filter(fn(DepositSubmission $depositSubmission): bool => $depositSubmission->status === DepositSubmissionStatus::Verified)
            ->sum('amount');
        $totalAllocatedAmount = (int) $allocations->sum('allocated_amount');
        $totalAllocatableAmount = $this->allocatableAmountFromTotals($totalVerifiedAmount, $totalAllocatedAmount);

        return [
            'total_deposit_amount' => $totalDepositAmount,
            'total_verified_amount' => $totalVerifiedAmount,
            'total_allocated_amount' => $totalAllocatedAmount,
            'total_allocatable_amount' => $totalAllocatableAmount,
            'total_deposit_count' => $deposits->count(),
            'can_allocate' => $totalAllocatableAmount >= DepositAllocation::DEFAULT_UNIT_AMOUNT && $hasApprovedMembers,
        ];
    }

    private function allocatableAmountFromTotals(int $totalVerifiedAmount, int $totalAllocatedAmount): int
    {
        return max(0, $totalVerifiedAmount - $totalAllocatedAmount);
    }

    private function transformDeposit(DepositSubmission $depositSubmission): array
    {
        return [
            'id' => $depositSubmission->id,
            'amount' => $depositSubmission->amount,
            'payment_method' => $depositSubmission->payment_method,
            'payment_method_label' => DepositSubmission::paymentMethodLabel($depositSubmission->payment_method),
            'reference_no' => $depositSubmission->reference_no,
            'deposit_date' => $depositSubmission->deposit_date?->format('d M Y'),
            'proof_url' => $depositSubmission->proof_path
                ? Storage::url($depositSubmission->proof_path)
                : null,
            'notes' => $depositSubmission->notes,
            'status' => $depositSubmission->status->value,
            'verified_at' => $depositSubmission->verified_at?->format('d M Y, h:i A'),
            'rejection_reason' => $depositSubmission->rejection_reason,
        ];
    }

    private function transformAllocation(DepositAllocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'member_name' => $allocation->member?->full_name,
            'allocation_month' => $allocation->allocation_month?->format('M Y'),
            'units' => $allocation->units,
            'allocated_amount' => $allocation->allocated_amount,
            'confirmed_at' => $allocation->confirmed_at?->format('d M Y, h:i A'),
        ];
    }

    private function managedAllocationsQuery(User $user)
    {
        return DepositAllocation::query()->whereHas(
            'member',
            fn($query) => $query->where('managed_by_user_id', $user->id),
        );
    }
}
