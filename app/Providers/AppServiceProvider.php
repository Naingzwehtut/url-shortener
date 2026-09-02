<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Laravel wraps a single JsonResource in {"data": {...}} by default.
        // This API's spec (and every test) expects the fields at the top
        // level — {"id": 1, "url": "...", "shortCode": "..."} — with no
        // envelope, so wrapping is disabled globally rather than per-resource.
        JsonResource::withoutWrapping();

        // Keyed by IP so one noisy client can't exhaust the budget for
        // everyone else. The limit itself is a config value, not a magic
        // number in a route file — see config/url_shortener.php and
        // README §24 for why the default is a placeholder, not a tuned one.
        RateLimiter::for('shorten', function (Request $request) {
            return Limit::perMinute(config('url_shortener.rate_limit_per_minute'))
                ->by($request->ip());
        });
    }
}
