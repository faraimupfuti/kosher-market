<?php

use App\Http\Controllers\ChatModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:customer')->group(function () {
    Route::post('/chat/{conversation}/block', [ChatModerationController::class, 'block'])->name('chat.block');
    Route::post('/chat/{conversation}/unblock', [ChatModerationController::class, 'unblock'])->name('chat.unblock');
    Route::post('/chat/{conversation}/report', [ChatModerationController::class, 'report'])->name('chat.report');
});

Route::middleware('auth:vendor')->group(function () {
    Route::post('/vendor/chat/{conversation}/block', [ChatModerationController::class, 'block'])->name('vendor.chat.block');
    Route::post('/vendor/chat/{conversation}/unblock', [ChatModerationController::class, 'unblock'])->name('vendor.chat.unblock');
    Route::post('/vendor/chat/{conversation}/report', [ChatModerationController::class, 'report'])->name('vendor.chat.report');
});
