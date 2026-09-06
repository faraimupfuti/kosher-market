<?php

use App\Http\Controllers\Store\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:customer')->group(function () {
    Route::get('/saved-searches', [SavedSearchController::class,'index'])->name('saved-searches.index');
    Route::post('/saved-searches', [SavedSearchController::class,'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class,'destroy'])->name('saved-searches.destroy');
});
