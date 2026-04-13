<?php

use App\Http\Controllers\Admin\MemberListController;
use App\Http\Controllers\Admin\ChargeCategoryController;
use App\Http\Controllers\Admin\ChargeListController;
use App\Http\Controllers\Admin\DepositListController;
use App\Http\Controllers\Admin\FundCycleController;
use App\Http\Controllers\Admin\GeneralExpenseController;
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberFundCycleController;
use App\Http\Controllers\MyAllocationController;
use App\Http\Controllers\MyChargeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('about-isf', 'AboutIsf', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('about');

Route::inertia('terms-and-conditions', 'TermsAndConditions', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('my-membership', [MemberController::class, 'index'])->name('members.index');
    Route::get('my-membership/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('my-membership', [MemberController::class, 'store'])->name('members.store');
    Route::get('my-membership/{member}/fund-cycles', [MemberFundCycleController::class, 'index'])->name('members.fund-cycles.index');
    Route::post('my-membership/{member}/fund-cycles/{fundCycle}/allocations', [MemberFundCycleController::class, 'store'])->name('members.fund-cycles.allocations.store');
    Route::get('my-deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::get('my-deposits/create', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('my-deposits', [DepositController::class, 'store'])->name('deposits.store');
    Route::post('my-deposits/allocate', [DepositController::class, 'storeAllocations'])->name('deposits.allocations.store');
    Route::get('my-allocations', [MyAllocationController::class, 'index'])->name('allocations.index');
    Route::get('my-charges', [MyChargeController::class, 'index'])->name('charges.index');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin/users', [UserListController::class, 'index'])->name('admin.users.index');
    Route::post('admin/users', [UserListController::class, 'store'])->name('admin.users.store');
    Route::put('admin/users/{user}', [UserListController::class, 'update'])->name('admin.users.update');
    Route::get('admin/fund-cycles', [FundCycleController::class, 'index'])->name('admin.fund-cycles.index');
    Route::post('admin/fund-cycles', [FundCycleController::class, 'store'])->name('admin.fund-cycles.store');
    Route::put('admin/fund-cycles/{fundCycle}', [FundCycleController::class, 'update'])->name('admin.fund-cycles.update');
    Route::post('admin/fund-cycles/{fundCycle}/allocations', [FundCycleController::class, 'storeAllocation'])->name('admin.fund-cycles.allocations.store');
    Route::get('admin/charge-categories', [ChargeCategoryController::class, 'index'])->name('admin.charge-categories.index');
    Route::post('admin/charge-categories', [ChargeCategoryController::class, 'store'])->name('admin.charge-categories.store');
    Route::put('admin/charge-categories/{chargeCategory}', [ChargeCategoryController::class, 'update'])->name('admin.charge-categories.update');
    Route::get('admin/general-expenses', [GeneralExpenseController::class, 'index'])->name('admin.general-expenses.index');
    Route::post('admin/general-expenses', [GeneralExpenseController::class, 'store'])->name('admin.general-expenses.store');
    Route::put('admin/general-expenses/{generalExpense}', [GeneralExpenseController::class, 'update'])->name('admin.general-expenses.update');
    Route::get('admin/members', [MemberListController::class, 'index'])->name('admin.members.index');
    Route::patch('admin/members/{member}/review', [MemberListController::class, 'review'])->name('admin.members.review');
    Route::get('admin/charges', [ChargeListController::class, 'index'])->name('admin.charges.index');
    Route::patch('admin/charges/{charge}/cancel', [ChargeListController::class, 'cancel'])->name('admin.charges.cancel');
    Route::get('admin/deposits', [DepositListController::class, 'index'])->name('admin.deposits.index');
    Route::patch('admin/deposits/{depositSubmission}/review', [DepositListController::class, 'review'])->name('admin.deposits.review');
});

require __DIR__ . '/settings.php';
