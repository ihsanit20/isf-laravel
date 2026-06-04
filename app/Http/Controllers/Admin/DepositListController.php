<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewDepositSubmissionRequest;
use App\Models\DepositSubmission;
use App\Models\User;
use App\Services\SmsService;
use App\Services\TreasuryBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepositListController extends Controller
{
    public function __construct(
        private readonly TreasuryBalanceService $treasuryBalance,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $paymentMethod = $request->string('payment_method')->toString();
        $search = trim($request->string('search')->toString());
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();
        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [15, 25, 50, 100, 200, 500], true) ? $perPage : 15;

        $depositsQuery = DepositSubmission::query()
            ->with(['user:id,name,email', 'verifier:id,name'])
            ->when(
                in_array($status, DepositSubmissionStatus::values(), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                in_array($paymentMethod, DepositSubmission::paymentMethods(), true),
                fn ($query) => $query->where('payment_method', $paymentMethod),
            )
            ->when($fromDate !== '', fn ($query) => $query->whereDate('deposit_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($query) => $query->whereDate('deposit_date', '<=', $toDate))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('deposit_date')
            ->latest('id');

        return Inertia::render('admin/Deposits', [
            'summary' => $this->treasuryBalance->summary(),
            'filters' => [
                'status' => $status,
                'payment_method' => $paymentMethod,
                'search' => $search,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => DepositSubmissionStatus::values(),
                'payment_methods' => collect(DepositSubmission::paymentMethods())
                    ->map(fn (string $method): array => [
                        'value' => $method,
                        'label' => DepositSubmission::paymentMethodLabel($method),
                    ])
                    ->values(),
            ],
            'deposits' => $depositsQuery
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (DepositSubmission $depositSubmission): array => [
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
                    'user' => [
                        'name' => $depositSubmission->user?->name,
                        'email' => $depositSubmission->user?->email,
                    ],
                    'verifier' => $depositSubmission->verifier?->name,
                ]),
        ]);
    }

    public function review(
        ReviewDepositSubmissionRequest $request,
        DepositSubmission $depositSubmission,
        SmsService $smsService,
    ): RedirectResponse {
        $status = DepositSubmissionStatus::from($request->string('status')->toString());

        $data = [
            'status' => $status,
            'rejection_reason' => $status === DepositSubmissionStatus::Rejected
                ? $request->string('rejection_reason')->toString()
                : null,
        ];

        if ($status === DepositSubmissionStatus::Verified) {
            /** @var User|null $user */
            $user = $request->user();

            $data['verified_at'] = now();
            $data['verified_by_user_id'] = $user?->id;
        } else {
            $data['verified_at'] = null;
            $data['verified_by_user_id'] = null;
        }

        $depositSubmission->update($data);

        if ($status === DepositSubmissionStatus::Verified) {
            $smsService->send(
                (string) ($depositSubmission->user?->phone ?? ''),
                sprintf(
                    'ISF deposit verified. Amount: BDT %d. Ref: %s.',
                    $depositSubmission->amount,
                    $depositSubmission->reference_no ?? 'N/A',
                ),
                $depositSubmission,
            );
        }

        return to_route('admin.deposits.index');
    }
}
