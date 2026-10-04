<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PayoutController;
use App\Ledger\Money;
use App\Ledger\Postings\MemberPostings;
use App\Models\PayoutRequest;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayoutListController extends Controller
{
    public function __construct(private readonly MemberPostings $memberPostings) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        return Inertia::render('admin/Payouts', [
            'filters' => ['status' => $status],
            'payouts' => PayoutRequest::query()
                ->with(['user:id,name,email,phone', 'processedBy:id,name'])
                ->when(in_array($status, ['pending', 'paid', 'rejected'], true), fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->get()
                ->map(fn (PayoutRequest $payout): array => [
                    ...PayoutController::transform($payout),
                    'user' => [
                        'id' => $payout->user_id,
                        'name' => $payout->user?->name,
                        'email' => $payout->user?->email,
                        'phone' => $payout->user?->phone,
                    ],
                    'available_balance' => Money::toTaka($this->memberPostings->availableBalance($payout->user_id)),
                    'processed_by' => $payout->processedBy?->name,
                ])
                ->values(),
        ]);
    }

    public function review(Request $request, PayoutRequest $payoutRequest, SmsService $smsService): RedirectResponse
    {
        $attributes = $request->validate([
            'status' => ['required', 'string', Rule::in([PayoutRequest::STATUS_PAID, PayoutRequest::STATUS_REJECTED])],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'rejection_reason' => ['nullable', 'string', 'max:255', 'required_if:status,rejected'],
        ]);

        if ($payoutRequest->status !== PayoutRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending payout requests can be processed.']);
        }

        DB::transaction(function () use ($request, $payoutRequest, $attributes): void {
            $payoutRequest->update([
                'status' => $attributes['status'],
                'reference_no' => $attributes['reference_no'] ?? null,
                'rejection_reason' => $attributes['status'] === PayoutRequest::STATUS_REJECTED ? $attributes['rejection_reason'] : null,
                'processed_at' => now(),
                'processed_by_user_id' => $request->user()?->id,
            ]);

            if ($attributes['status'] === PayoutRequest::STATUS_PAID) {
                $this->memberPostings->lockAndAssertAvailable(
                    $payoutRequest->user_id,
                    Money::toPaisa($payoutRequest->amount),
                    'status',
                    'The user no longer has enough available balance for this payout.',
                );
                $this->memberPostings->payoutPaid($payoutRequest, $request->user());
            }
        });

        if ($attributes['status'] === PayoutRequest::STATUS_PAID) {
            $smsService->send(
                (string) ($payoutRequest->user?->phone ?? ''),
                sprintf('ISF payout sent. Amount: BDT %s. Ref: %s.', number_format((float) $payoutRequest->amount, 2), $payoutRequest->reference_no ?? 'N/A'),
                $payoutRequest,
            );
        }

        return back();
    }
}
