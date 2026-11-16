<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAllocationOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserAllocationController extends Controller
{
    public function show(Request $request, User $user, UserAllocationOverview $overview): Response
    {
        return Inertia::render('Allocations', [
            ...$overview->forUser($user),
            'selectedMemberId' => $request->integer('member') ?: null,
            'selectedCycleId' => $request->integer('cycle') ?: null,
            'readOnly' => true,
            'viewingUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
