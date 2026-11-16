<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAllocationOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyAllocationController extends Controller
{
    public function index(Request $request, UserAllocationOverview $overview): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Allocations', [
            ...$overview->forUser($user),
            'selectedMemberId' => $request->integer('member') ?: null,
            'selectedCycleId' => $request->integer('cycle') ?: null,
        ]);
    }
}
