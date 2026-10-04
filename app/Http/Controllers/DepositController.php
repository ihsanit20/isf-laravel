<?php

namespace App\Http\Controllers;

use App\Enums\DepositSubmissionStatus;
use App\Enums\MemberStatus;
use App\Http\Requests\Deposits\StoreDepositAllocationRequest;
use App\Http\Requests\Deposits\StoreDepositSubmissionRequest;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\Charge;
use App\Models\ChargeAllocation;
use App\Models\ChargeCategory;
use App\Models\DepositSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function __construct(private readonly MemberPostings $memberPostings) {}

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
            $this->pendingChargesQuery($user)->exists(),
            $this->memberPostings->balanceBreakdown($user->id),
        );

        return Inertia::render('Deposits', [
            'summary' => $summary,
            'deposits' => $deposits
                ->map(fn (DepositSubmission $depositSubmission): array => $this->transformDeposit($depositSubmission))
                ->values(),
            'chargeAllocations' => $chargeAllocations
                ->map(fn (ChargeAllocation $allocation): array => $this->transformChargeAllocation($allocation))
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

        $proofPath = $request->file('proof')?->store('deposit-proofs', DepositSubmission::proofDisk());

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
            $chargeIds = collect($request->validated('charge_ids', []))->map(fn ($id) => (int) $id)->all();

            $charges = Charge::query()
                ->with(['category', 'member'])
                ->whereIn('status', [Charge::STATUS_PENDING, Charge::STATUS_CANCELLED])
                ->whereIn('id', $chargeIds)
                ->whereHas('member', fn ($query) => $query
                    ->where('managed_by_user_id', $user->id)
                    ->where('status', MemberStatus::Approved))
                ->lockForUpdate()
                ->get();

            if ($charges->isEmpty()) {
                throw ValidationException::withMessages([
                    'charge_ids' => 'Select at least one pending charge to settle.',
                ]);
            }

            $this->memberPostings->lockAndAssertAvailable(
                $user->id,
                Money::toPaisa($charges->sum('amount')),
                'charge_ids',
                'Charge allocation cannot exceed your available balance.',
            );

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

    /**
     * Submitted totals come from the deposit documents (pending and rejected
     * deposits never reach the journal); every money figure comes from the
     * journal, so verified − allocated = allocatable always holds.
     *
     * @param  array{deposits: int, fees: int, cycle_allocations: int, cycle_returns: int, payouts: int, other: int, available: int}  $balance
     */
    private function buildDepositSummary(Collection $deposits, bool $hasPendingCharges, array $balance): array
    {
        $totalRejectedDepositCount = $deposits
            ->filter(fn (DepositSubmission $depositSubmission): bool => $depositSubmission->status === DepositSubmissionStatus::Rejected)
            ->count();

        return [
            'total_deposit_amount' => round((float) $deposits->sum('amount'), 2),
            'total_verified_amount' => Money::toTaka($balance['deposits']),
            'total_rejected_deposit_count' => $totalRejectedDepositCount,
            'total_charge_allocated_amount' => Money::toTaka($balance['fees']),
            'total_fund_cycle_allocated_amount' => Money::toTaka($balance['cycle_allocations']),
            'total_cycle_returned_amount' => Money::toTaka($balance['cycle_returns']),
            'total_payout_amount' => Money::toTaka($balance['payouts']),
            'total_allocated_amount' => Money::toTaka($balance['deposits'] - $balance['available']),
            'total_allocatable_amount' => Money::toTaka($balance['available']),
            'total_deposit_count' => $deposits->count(),
            'can_allocate' => $balance['available'] > 0 && $hasPendingCharges,
        ];
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
            'proof_url' => $depositSubmission->proofUrl(),
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
            fn ($query) => $query->where('managed_by_user_id', $user->id),
        );
    }

    private function pendingChargesQuery(User $user)
    {
        return Charge::query()
            ->where('status', Charge::STATUS_PENDING)
            ->whereHas('member', fn ($query) => $query
                ->where('managed_by_user_id', $user->id)
                ->where('status', MemberStatus::Approved));
    }
}
