<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Requests\Deposits\StoreDepositAllocationRequest;
use App\Http\Requests\Deposits\StoreDepositSubmissionRequest;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\User;
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

        $chargeAllocations = $this->managedChargeAllocationsQuery($user)
            ->with(['charge.category:id,title,code', 'charge.member:id,full_name'])
            ->latest('confirmed_at')
            ->latest('id')
            ->get();

        $summary = $this->buildDepositSummary(
            $deposits,
            $chargeAllocations,
            $this->pendingChargesQuery($user)->exists(),
        );

        return Inertia::render('Deposits', [
            'summary' => $summary,
            'deposits' => $deposits
                ->map(fn(DepositSubmission $depositSubmission): array => $this->transformDeposit($depositSubmission))
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

            $existingChargeAllocations = $this->managedChargeAllocationsQuery($user)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->get();

            if ($verifiedDeposits->isEmpty()) {
                throw ValidationException::withMessages([
                    'charge_ids' => 'No verified deposits are available for charge settlement.',
                ]);
            }

            $chargeIds = collect($request->validated('charge_ids', []))->map(fn($id) => (int) $id)->all();

            $charges = Charge::query()
                ->with(['category', 'member'])
                ->whereIn('status', [Charge::STATUS_PENDING, Charge::STATUS_CANCELLED])
                ->whereIn('id', $chargeIds)
                ->whereHas('member', fn($query) => $query
                    ->where('managed_by_user_id', $user->id)
                    ->where('status', MemberStatus::Approved))
                ->lockForUpdate()
                ->get();

            $totalAllocatedAmount = (int) $charges->sum('amount');

            $totalAllocatableAmount = $this->allocatableAmountFromTotals(
                (int) $verifiedDeposits->sum('amount'),
                (int) $existingChargeAllocations->sum('amount'),
            );

            if ($totalAllocatedAmount > $totalAllocatableAmount) {
                throw ValidationException::withMessages([
                    'charge_ids' => 'Charge allocation cannot exceed the total allocatable verified deposit amount.',
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

    private function buildDepositSummary(Collection $deposits, Collection $chargeAllocations, bool $hasPendingCharges): array
    {
        $totalDepositAmount = (int) $deposits->sum('amount');
        $totalVerifiedAmount = (int) $deposits
            ->filter(fn(DepositSubmission $depositSubmission): bool => $depositSubmission->status === DepositSubmissionStatus::Verified)
            ->sum('amount');
        $totalChargeAllocatedAmount = (int) $chargeAllocations
            ->filter(fn(ChargeAllocation $allocation): bool => $allocation->reversed_at === null)
            ->sum('amount');
        $totalAllocatedAmount = $totalChargeAllocatedAmount;
        $totalAllocatableAmount = $this->allocatableAmountFromTotals($totalVerifiedAmount, $totalChargeAllocatedAmount);

        return [
            'total_deposit_amount' => $totalDepositAmount,
            'total_verified_amount' => $totalVerifiedAmount,
            'total_charge_allocated_amount' => $totalChargeAllocatedAmount,
            'total_allocated_amount' => $totalAllocatedAmount,
            'total_allocatable_amount' => $totalAllocatableAmount,
            'total_deposit_count' => $deposits->count(),
            'can_allocate' => $totalAllocatableAmount > 0 && $hasPendingCharges,
        ];
    }

    private function allocatableAmountFromTotals(int $totalVerifiedAmount, int $totalChargeAllocatedAmount): int
    {
        return max(0, $totalVerifiedAmount - $totalChargeAllocatedAmount);
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
