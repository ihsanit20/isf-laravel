<?php

namespace App\Http\Controllers;

use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user withdraws money from their available balance.
 */
class PayoutController extends Controller
{
    public function __construct(private readonly MemberPostings $memberPostings) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Payouts', [
            'summary' => $this->summaryFor($user),
            'paymentMethods' => collect(PayoutRequest::PAYMENT_METHODS)
                ->map(fn (string $method): array => ['value' => $method, 'label' => str($method)->replace('_', ' ')->title()->toString()])
                ->values(),
            'payouts' => PayoutRequest::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->get()
                ->map(fn (PayoutRequest $payout): array => self::transform($payout))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attributes = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', Rule::in(PayoutRequest::PAYMENT_METHODS)],
            'account_details' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $attributes): void {
            $this->memberPostings->lockAndAssertAvailable(
                $user->id,
                Money::toPaisa($attributes['amount']) + $this->pendingPaisa($user->id),
                'amount',
                'Payout request exceeds your available balance (pending requests included).',
            );

            PayoutRequest::query()->create([
                ...$attributes,
                'user_id' => $user->id,
                'status' => PayoutRequest::STATUS_PENDING,
            ]);
        });

        return to_route('payouts.index');
    }

    /**
     * @return array{available_balance: float, pending_amount: float, requestable_amount: float}
     */
    private function summaryFor(User $user): array
    {
        $available = $this->memberPostings->availableBalance($user->id);
        $pending = $this->pendingPaisa($user->id);

        return [
            'available_balance' => Money::toTaka($available),
            'pending_amount' => Money::toTaka($pending),
            'requestable_amount' => Money::toTaka(max(0, $available - $pending)),
        ];
    }

    private function pendingPaisa(int $userId): int
    {
        return Money::toPaisa(PayoutRequest::query()
            ->where('user_id', $userId)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->sum('amount'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function transform(PayoutRequest $payout): array
    {
        return [
            'id' => $payout->id,
            'amount' => (float) $payout->amount,
            'payment_method' => $payout->payment_method,
            'payment_method_label' => str($payout->payment_method)->replace('_', ' ')->title()->toString(),
            'account_details' => $payout->account_details,
            'notes' => $payout->notes,
            'status' => $payout->status,
            'reference_no' => $payout->reference_no,
            'rejection_reason' => $payout->rejection_reason,
            'requested_at' => $payout->created_at?->format('d M Y, h:i A'),
            'processed_at' => $payout->processed_at?->format('d M Y, h:i A'),
        ];
    }
}
