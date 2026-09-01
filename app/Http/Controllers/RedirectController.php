<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Url;
use App\Models\UrlClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RedirectController extends Controller
{
    public function __invoke(Request $request, string $shortCode): RedirectResponse
    {
        // Cache-aside read: this is the hottest path in the whole API (every
        // visitor hits it, not just API consumers), so it's the one lookup
        // worth caching. See README §22 for the cache-aside pattern, TTL
        // reasoning, and why staleness here is bounded and low-risk.
        $url = Cache::remember(
            Url::cacheKey($shortCode),
            Url::CACHE_TTL_SECONDS,
            fn () => Url::where('short_code', $shortCode)->firstOrFail(),
        );

        // Both writes below go straight to Postgres, never to the cache —
        // access_count and click logs must always be exact, never subject
        // to cache staleness or eviction.
        $url->incrementAccessCount();

        UrlClick::create([
            'url_id' => $url->id,
            'accessed_at' => now(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
        ]);

        // 302 Found, not 301 Moved Permanently:
        // A 301 tells browsers and crawlers "cache this redirect forever
        // and stop asking the origin" — which would (a) make access_count
        // stop incrementing for repeat visitors whose browser now redirects
        // locally without ever hitting our server again, and (b) make the
        // mapping impossible to change later (Update endpoint would become
        // pointless for cached clients). 302 says "this is where it points
        // *right now*", which matches a resource that is explicitly
        // updatable and whose whole point is to be measured on every hit.
        return redirect($url->url, 302);
    }
}
