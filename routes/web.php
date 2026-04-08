<?php

use App\Http\Controllers\Admin\MemberListController;
use App\Http\Controllers\Admin\DepositListController;
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('my-membership', [MemberController::class, 'index'])->name('members.index');
    Route::get('my-membership/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('my-membership', [MemberController::class, 'store'])->name('members.store');
    Route::get('deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::get('deposits/create', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('deposits', [DepositController::class, 'store'])->name('deposits.store');
    Route::get('deposits/{depositSubmission}/allocate', [DepositController::class, 'allocate'])->name('deposits.allocate');
    Route::post('deposits/{depositSubmission}/allocate', [DepositController::class, 'storeAllocations'])->name('deposits.allocations.store');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin/users', [UserListController::class, 'index'])->name('admin.users.index');
    Route::post('admin/users', [UserListController::class, 'store'])->name('admin.users.store');
    Route::put('admin/users/{user}', [UserListController::class, 'update'])->name('admin.users.update');
    Route::get('admin/members', [MemberListController::class, 'index'])->name('admin.members.index');
    Route::patch('admin/members/{member}/review', [MemberListController::class, 'review'])->name('admin.members.review');
    Route::get('admin/deposits', [DepositListController::class, 'index'])->name('admin.deposits.index');
    Route::patch('admin/deposits/{depositSubmission}/review', [DepositListController::class, 'review'])->name('admin.deposits.review');
});

require __DIR__ . '/settings.php';
