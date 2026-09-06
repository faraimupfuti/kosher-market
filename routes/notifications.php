<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:customer')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});

Route::middleware('auth:vendor')->group(function () {
    Route::get('/vendor/notifications', [NotificationController::class, 'index'])->name('vendor.notifications.index');
    Route::post('/vendor/notifications/{id}/read', [NotificationController::class, 'read'])->name('vendor.notifications.read');
    Route::post('/vendor/notifications/read-all', [NotificationController::class, 'readAll'])->name('vendor.notifications.read-all');
});
