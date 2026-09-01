<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUrlRequest;
use App\Http\Requests\UpdateUrlRequest;
use App\Http\Resources\UrlAnalyticsResource;
use App\Http\Resources\UrlResource;
use App\Http\Resources\UrlStatsResource;
use App\Models\Url;
use App\Services\UrlShortenerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UrlController extends Controller
{
    public function __construct(
        private readonly UrlShortenerService $shortener,
    ) {
    }

    public function store(StoreUrlRequest $request): JsonResponse
    {
        $url = $this->shortener->create($request->validated('url'));

        return (new UrlResource($url))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $shortCode): UrlResource
    {
        return new UrlResource($this->findCached($shortCode));
    }

    public function update(UpdateUrlRequest $request, string $shortCode): UrlResource
    {
        // Deliberately queried fresh, not from cache: a write path should
        // never operate on a possibly-stale cached copy.
        $url = Url::where('short_code', $shortCode)->firstOrFail();

        // short_code is intentionally never part of $request->validated() —
        // there is no code path that lets a client change it.
        $url->update([
            'url' => $request->validated('url'),
        ]);

        $this->forgetCache($shortCode);

        return new UrlResource($url);
    }

    public function destroy(string $shortCode): Response
    {
        $url = Url::where('short_code', $shortCode)->firstOrFail();
        $url->delete();

        $this->forgetCache($shortCode);

        return response()->noContent(); // 204
    }

    public function stats(string $shortCode): UrlStatsResource
    {
        // access_count must reflect reality on every call, so this is a
        // deliberate cache bypass — see README §22 for why stats/analytics
        // reads are never served from cache while the redirect lookup is.
        $url = Url::where('short_code', $shortCode)->firstOrFail();

        return new UrlStatsResource($url);
    }

    public function analytics(string $shortCode): UrlAnalyticsResource
    {
        $url = Url::where('short_code', $shortCode)->firstOrFail();

        $now = now();

        $clicksToday = $url->clicks()->whereDate('accessed_at', $now->toDateString())->count();
        $clicksThisWeek = $url->clicks()->whereBetween('accessed_at', [
            $now->copy()->startOfWeek(),
            $now->copy()->endOfWeek(),
        ])->count();
        $lastAccessedAt = $url->clicks()->latest('accessed_at')->first()?->accessed_at;

        $clicksByDate = $url->clicks()
            ->select(DB::raw('DATE(accessed_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'count' => (int) $row->count])
            ->values()
            ->all();

        return new UrlAnalyticsResource($url, [
            'totalClicks' => $url->clicks()->count(),
            'clicksToday' => $clicksToday,
            'clicksThisWeek' => $clicksThisWeek,
            'lastAccessedAt' => $lastAccessedAt,
            'clicksByDate' => $clicksByDate,
        ]);
    }

    private function findCached(string $shortCode): Url
    {
        // Cache-aside: check Redis first; on miss, hit Postgres and
        // populate the cache for next time. A thrown ModelNotFoundException
        // inside the closure propagates out of remember() without being
        // cached, so 404s are never cached — see README §22.
        return Cache::remember(
            Url::cacheKey($shortCode),
            Url::CACHE_TTL_SECONDS,
            fn () => Url::where('short_code', $shortCode)->firstOrFail(),
        );
    }

    private function forgetCache(string $shortCode): void
    {
        Cache::forget(Url::cacheKey($shortCode));
    }
}
