<?php

namespace App\Http\Controllers;

use App\Services\EventBkashPaymentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BkashCallbackController extends Controller
{
    public function __construct(
        private readonly EventBkashPaymentService $bkashPayments,
    ) {}

    public function handle(Request $request): Response
    {
        return $this->bkashPayments->handleCallback($request);
    }
}
