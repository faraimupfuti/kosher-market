<?php

use App\Http\Controllers\DisputeController;
use App\Http\Controllers\OrderTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:customer')->group(function () {
    Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show'])->name('disputes.show');
    Route::post('/orders/{order}/dispute', [DisputeController::class, 'store'])->name('disputes.store');
    Route::post('/disputes/{dispute}/evidence', [DisputeController::class, 'evidence'])->name('disputes.evidence');
    Route::get('/disputes/evidence/{evidence}', [DisputeController::class, 'downloadEvidence'])->name('disputes.evidence.download');
    Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'show'])->name('orders.tracking');
});

Route::middleware('auth:vendor')->group(function () {
    Route::get('/disputes', [DisputeController::class, 'index'])->name('vendor.disputes.index');
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show'])->name('vendor.disputes.show');
    Route::post('/disputes/{dispute}/respond', [DisputeController::class, 'respond'])->name('disputes.respond');
    Route::post('/disputes/{dispute}/evidence', [DisputeController::class, 'evidence'])->name('vendor.disputes.evidence');
    Route::get('/disputes/evidence/{evidence}', [DisputeController::class, 'downloadEvidence'])->name('vendor.disputes.evidence.download');
    Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'show'])->name('vendor.orders.tracking');
    Route::post('/orders/{order}/tracking', [OrderTrackingController::class, 'store'])->name('orders.tracking.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/disputes/{dispute}/resolve', [DisputeController::class, 'resolve'])->name('admin.disputes.resolve');
});
