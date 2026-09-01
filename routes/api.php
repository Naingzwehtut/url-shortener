<?php

use App\Http\Controllers\UrlController;
use Illuminate\Support\Facades\Route;

Route::prefix('shorten')->group(function () {
    // Named limiter ('shorten') defined in AppServiceProvider so the limit
    // is one config value (SHORTEN_RATE_LIMIT_PER_MINUTE), not a magic
    // number buried in a route file. Still a conservative, un-tuned
    // default — see README §24 for the phase that revisits this against
    // real traffic.
    Route::post('', [UrlController::class, 'store'])->middleware('throttle:shorten');
    Route::get('/{shortCode}', [UrlController::class, 'show']);
    Route::put('/{shortCode}', [UrlController::class, 'update']);
    Route::delete('/{shortCode}', [UrlController::class, 'destroy']);
    Route::get('/{shortCode}/stats', [UrlController::class, 'stats']);
    Route::get('/{shortCode}/analytics', [UrlController::class, 'analytics']);
});
