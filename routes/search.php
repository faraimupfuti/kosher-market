<?php

use App\Http\Controllers\Store\SavedSearchController;
use App\Http\Controllers\Store\SmartSearchController;
use Illuminate\Support\Facades\Route;

Route::post('/smart-search', [SmartSearchController::class, 'interpret'])->name('smart-search');
Route::middleware('auth:customer')->group(function () {
    Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
});
