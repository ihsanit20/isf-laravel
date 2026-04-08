<?php

use App\Http\Controllers\Admin\UserListController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin/users', [UserListController::class, 'index'])->name('admin.users.index');
    Route::post('admin/users', [UserListController::class, 'store'])->name('admin.users.store');
    Route::put('admin/users/{user}', [UserListController::class, 'update'])->name('admin.users.update');
});

require __DIR__ . '/settings.php';
