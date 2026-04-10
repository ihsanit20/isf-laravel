<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Requests\Deposits\StoreDepositAllocationRequest;
use App\Http\Requests\Deposits\StoreDepositSubmissionRequest;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
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

        $chargeAllocations = $this->managedChargeAllocationsQuery($user)
            ->with(['charge.category:id,title,code', 'charge.member:id,full_name'])
            ->latest('confirmed_at')
            ->latest('id')
            ->get();

        $summary = $this->buildDepositSummary(
            $deposits,
            $allocations,
            $chargeAllocations,
            $user->managedMembers()->where('status', MemberStatus::Approved)->whereNotNull('activated_at')->exists(),
            $this->pendingChargesQuery($user)->exists(),
        );

        return Inertia::render('Deposits', [
            'summary' => $summary,
            'deposits' => $deposits
                ->map(fn(DepositSubmission $depositSubmission): array => $this->transformDeposit($depositSubmission))
                ->values(),
            'allocations' => $allocations
                ->map(fn(DepositAllocation $allocation): array => $this->transformAllocation($allocation))
                ->values(),
            'chargeAllocations' => $chargeAllocations
                ->map(fn(ChargeAllocation $allocation): array => $this->transformChargeAllocation($allocation))
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
        $chargeAllocations = $this->managedChargeAllocationsQuery($user)->get();
        $pendingCharges = $this->pendingChargesQuery($user)
            ->with(['category:id,title,code', 'member:id,full_name,activated_at'])
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('deposits/Allocate', [
            'summary' => $this->buildDepositSummary(
                $deposits,
                $allocations,
                $chargeAllocations,
                true,
                $pendingCharges->isNotEmpty(),
            ),
            'members' => Member::query()
                ->where('managed_by_user_id', $user->id)
                ->where('status', MemberStatus::Approved)
                ->whereNotNull('activated_at')
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
            'charges' => $pendingCharges
                ->map(fn(Charge $charge): array => [
                    'id' => $charge->id,
                    'amount' => $charge->amount,
                    'category_title' => $charge->category?->title,
                    'category_code' => $charge->category?->code,
                    'member_name' => $charge->member?->full_name,
                    'effective_at' => $charge->effective_at?->format('d M Y, h:i A'),
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

            $existingChargeAllocations = $this->managedChargeAllocationsQuery($user)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->get();

            if ($verifiedDeposits->isEmpty()) {
                throw ValidationException::withMessages([
                    'unit_rows' => 'No verified deposits are available for allocation.',
                ]);
            }

            $unitRows = collect($request->validated('unit_rows', []));
            $chargeIds = collect($request->validated('charge_ids', []))->map(fn($id) => (int) $id)->all();

            $charges = Charge::query()
                ->with(['category', 'member'])
                ->where('status', Charge::STATUS_PENDING)
                ->whereIn('id', $chargeIds)
                ->whereHas('member', fn($query) => $query
                    ->where('managed_by_user_id', $user->id)
                    ->where('status', MemberStatus::Approved))
                ->lockForUpdate()
                ->get();

            $totalAllocatedAmount = $unitRows->sum(
                fn(array $row): int => (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT,
            ) + (int) $charges->sum('amount');

            $totalAllocatableAmount = $this->allocatableAmountFromTotals(
                (int) $verifiedDeposits->sum('amount'),
                (int) $existingAllocations->sum('allocated_amount'),
                (int) $existingChargeAllocations->sum('amount'),
            );

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                throw ValidationException::withMessages([
                    'unit_rows' => 'Allocated amount cannot exceed the total allocatable verified deposit amount.',
                ]);
            }

            foreach ($unitRows as $row) {
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

            foreach ($charges as $charge) {
                ChargeAllocation::query()->create([
                    'charge_id' => $charge->id,
                    'amount' => $charge->amount,
                    'confirmed_at' => now(),
                ]);

                $charge->update([
                    'status' => Charge::STATUS_POSTED,
                ]);

                if ($charge->category?->code === ChargeCategory::CODE_REGISTRATION_FEE && $charge->member?->activated_at === null) {
                    $charge->member?->update([
                        'activated_at' => now(),
                    ]);
                }
            }
        });

        return to_route('deposits.index');
    }

    private function buildDepositSummary(Collection $deposits, Collection $allocations, Collection $chargeAllocations, bool $hasActiveMembers, bool $hasPendingCharges): array
    {
        $totalDepositAmount = (int) $deposits->sum('amount');
        $totalVerifiedAmount = (int) $deposits
            ->filter(fn(DepositSubmission $depositSubmission): bool => $depositSubmission->status === DepositSubmissionStatus::Verified)
            ->sum('amount');
        $totalUnitAllocatedAmount = (int) $allocations->sum('allocated_amount');
        $totalChargeAllocatedAmount = (int) $chargeAllocations
            ->filter(fn(ChargeAllocation $allocation): bool => $allocation->reversed_at === null)
            ->sum('amount');
        $totalAllocatedAmount = $totalUnitAllocatedAmount + $totalChargeAllocatedAmount;
        $totalAllocatableAmount = $this->allocatableAmountFromTotals($totalVerifiedAmount, $totalUnitAllocatedAmount, $totalChargeAllocatedAmount);

        return [
            'total_deposit_amount' => $totalDepositAmount,
            'total_verified_amount' => $totalVerifiedAmount,
            'total_unit_allocated_amount' => $totalUnitAllocatedAmount,
            'total_charge_allocated_amount' => $totalChargeAllocatedAmount,
            'total_allocated_amount' => $totalAllocatedAmount,
            'total_allocatable_amount' => $totalAllocatableAmount,
            'total_deposit_count' => $deposits->count(),
            'can_allocate' => $totalAllocatableAmount > 0 && ($hasActiveMembers || $hasPendingCharges),
        ];
    }

    private function allocatableAmountFromTotals(int $totalVerifiedAmount, int $totalUnitAllocatedAmount, int $totalChargeAllocatedAmount): int
    {
        return max(0, $totalVerifiedAmount - $totalUnitAllocatedAmount - $totalChargeAllocatedAmount);
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

    private function transformChargeAllocation(ChargeAllocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'member_name' => $allocation->charge?->member?->full_name,
            'charge_title' => $allocation->charge?->category?->title,
            'amount' => $allocation->amount,
            'confirmed_at' => $allocation->confirmed_at?->format('d M Y, h:i A'),
            'reversed_at' => $allocation->reversed_at?->format('d M Y, h:i A'),
        ];
    }

    private function managedAllocationsQuery(User $user)
    {
        return DepositAllocation::query()->whereHas(
            'member',
            fn($query) => $query->where('managed_by_user_id', $user->id),
        );
    }

    private function managedChargeAllocationsQuery(User $user)
    {
        return ChargeAllocation::query()->whereHas(
            'charge.member',
            fn($query) => $query->where('managed_by_user_id', $user->id),
        );
    }

    private function pendingChargesQuery(User $user)
    {
        return Charge::query()
            ->where('status', Charge::STATUS_PENDING)
            ->whereHas('member', fn($query) => $query
                ->where('managed_by_user_id', $user->id)
                ->where('status', MemberStatus::Approved));
    }
}
