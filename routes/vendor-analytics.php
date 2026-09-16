<?php

use App\Http\Controllers\Vendor\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:vendor')->prefix('vendor')->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('vendor.analytics');
});
