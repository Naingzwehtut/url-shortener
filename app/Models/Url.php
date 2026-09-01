<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    use HasFactory;

    /**
     * How long a short_code -> URL mapping stays cached. This only bounds
     * the staleness window for the *target URL* (via update/delete) — it
     * has no bearing on access_count accuracy, since that's always read
     * fresh from the database on the stats/analytics endpoints and always
     * written straight to the database (never to cache) on every redirect.
     */
    public const CACHE_TTL_SECONDS = 3600;

    public static function cacheKey(string $shortCode): string
    {
        return "url:short_code:{$shortCode}";
    }

    /**
     * Mass assignment protection: only these fields can ever be filled via
     * create()/update() arrays. short_code and access_count ARE listed
     * here because UrlShortenerService and the test factory legitimately
     * mass-assign them directly — but no client input can ever reach
     * either, because StoreUrlRequest/UpdateUrlRequest only ever validate
     * and expose a single 'url' key. The real protection boundary is the
     * Form Request's rules(), not this array; on the redirect path,
     * access_count is never touched via mass assignment at all — it only
     * ever changes via the atomic increment() in incrementAccessCount().
     */
    protected $fillable = [
        'url',
        'short_code',
        'access_count',
    ];

    protected $casts = [
        'access_count' => 'integer',
    ];

    public function incrementAccessCount(): void
    {
        // Atomic SQL-level UPDATE ... SET access_count = access_count + 1.
        // See UrlShortenerService for the full explanation of why this
        // matters under concurrent redirects.
        $this->increment('access_count');
    }

    public function clicks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UrlClick::class);
    }
}
