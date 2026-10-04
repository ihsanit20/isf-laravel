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

/**
 * A user withdraws money from their available balance.
 */
class PayoutController extends Controller
{
    public function __construct(private readonly MemberPostings $memberPostings) {}

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

        return to_route('wallet.index');
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
