<?php

use App\Http\Controllers\Api\PublicBkashPaymentController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Controllers\Api\PublicOrderTrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public event endpoints
    Route::get('events', [PublicEventController::class, 'index'])->name('api.events.index');
    Route::get('events/{slug}', [PublicEventController::class, 'show'])->name('api.events.show');

    // Public order placement
    Route::post('orders', [PublicOrderController::class, 'store'])->name('api.orders.store');

    // bKash advance payment
    Route::post('orders/{orderNumber}/bkash/init', [PublicBkashPaymentController::class, 'init'])
        ->name('api.orders.bkash.init');
    Route::post('orders/{orderNumber}/bkash/init-due', [PublicBkashPaymentController::class, 'initDue'])
        ->name('api.orders.bkash.init-due');

    // Public order tracking
    Route::get('orders/track', [PublicOrderTrackingController::class, 'show'])->name('api.orders.track');
    Route::get('orders/track-by-token', [PublicOrderTrackingController::class, 'showByToken'])
        ->name('api.orders.track-by-token');
});
