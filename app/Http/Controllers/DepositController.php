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

        return Inertia::render('Deposits', [
            'deposits' => $user->depositSubmissions()
                ->with(['allocations.member:id,full_name'])
                ->latest('deposit_date')
                ->latest('id')
                ->get()
                ->map(fn(DepositSubmission $depositSubmission): array => $this->transformDeposit($depositSubmission))
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

    public function allocate(Request $request, DepositSubmission $depositSubmission): Response
    {
        abort_unless($depositSubmission->user_id === $request->user()?->id, 403);

        $depositSubmission->load('allocations.member:id,full_name');

        abort_unless($depositSubmission->status === DepositSubmissionStatus::Verified, 403);

        return Inertia::render('deposits/Allocate', [
            'deposit' => $this->transformDeposit($depositSubmission),
            'members' => Member::query()
                ->where('managed_by_user_id', $request->user()?->id)
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

    public function storeAllocations(StoreDepositAllocationRequest $request, DepositSubmission $depositSubmission): RedirectResponse
    {
        abort_unless($depositSubmission->user_id === $request->user()?->id, 403);

        DB::transaction(function () use ($request, $depositSubmission): void {
            /** @var DepositSubmission $lockedDeposit */
            $lockedDeposit = DepositSubmission::query()
                ->lockForUpdate()
                ->findOrFail($depositSubmission->id);

            if ($lockedDeposit->status !== DepositSubmissionStatus::Verified) {
                throw ValidationException::withMessages([
                    'rows' => 'Only verified deposits can be allocated.',
                ]);
            }

            $rows = collect($request->validated('rows'));
            $totalAllocatedAmount = $rows->sum(
                fn(array $row): int => (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT,
            );

            if ($totalAllocatedAmount > $lockedDeposit->remainingAmount()) {
                throw ValidationException::withMessages([
                    'rows' => 'Allocated amount cannot exceed the remaining verified deposit amount.',
                ]);
            }

            $lockedDeposit->allocations()->createMany(
                $rows->map(fn(array $row): array => [
                    'member_id' => (int) $row['member_id'],
                    'allocation_month' => CarbonImmutable::createFromFormat('Y-m', $row['allocation_month'])
                        ->startOfMonth()
                        ->toDateString(),
                    'units' => (int) $row['units'],
                    'unit_amount' => DepositAllocation::DEFAULT_UNIT_AMOUNT,
                    'allocated_amount' => (int) $row['units'] * DepositAllocation::DEFAULT_UNIT_AMOUNT,
                    'confirmed_at' => now(),
                ])->all(),
            );
        });

        return to_route('deposits.index');
    }

    private function transformDeposit(DepositSubmission $depositSubmission): array
    {
        $allocatedAmount = $depositSubmission->allocatedAmount();
        $remainingAmount = max(0, $depositSubmission->amount - $allocatedAmount);

        return [
            'id' => $depositSubmission->id,
            'amount' => $depositSubmission->amount,
            'allocated_amount' => $allocatedAmount,
            'remaining_amount' => $remainingAmount,
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
            'can_allocate' => $depositSubmission->status === DepositSubmissionStatus::Verified && $remainingAmount > 0,
            'allocations' => $depositSubmission->allocations
                ->map(fn(DepositAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'member_name' => $allocation->member?->full_name,
                    'allocation_month' => $allocation->allocation_month?->format('M Y'),
                    'units' => $allocation->units,
                    'allocated_amount' => $allocation->allocated_amount,
                    'confirmed_at' => $allocation->confirmed_at?->format('d M Y, h:i A'),
                ])
                ->values(),
        ];
    }
}
