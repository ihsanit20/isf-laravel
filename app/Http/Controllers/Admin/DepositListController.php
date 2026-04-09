<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewDepositSubmissionRequest;
use App\Models\DepositSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DepositListController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Deposits', [
            'deposits' => DepositSubmission::query()
                ->with(['user:id,name,email', 'verifier:id,name'])
                ->latest('deposit_date')
                ->latest('id')
                ->get()
                ->map(fn(DepositSubmission $depositSubmission): array => [
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
                    'user' => [
                        'name' => $depositSubmission->user?->name,
                        'email' => $depositSubmission->user?->email,
                    ],
                    'verifier' => $depositSubmission->verifier?->name,
                ])
                ->values(),
        ]);
    }

    public function review(ReviewDepositSubmissionRequest $request, DepositSubmission $depositSubmission): RedirectResponse
    {
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

        return to_route('admin.deposits.index');
    }
}
