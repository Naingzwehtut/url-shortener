<?php

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

// GET /{shortCode} — the actual short URL a client clicks on.
// Placed last / kept minimal in web.php so it doesn't collide with any
// future web routes; constrained to the short-code alphabet so it never
// swallows requests to e.g. /favicon.ico.
Route::get('/{shortCode}', RedirectController::class)
    ->where('shortCode', '[A-Za-z0-9]{1,10}')
    ->name('redirect.short-code');
