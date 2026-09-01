<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieving_a_url_populates_the_cache(): void
    {
        $url = Url::factory()->create();

        $this->assertFalse(Cache::has(Url::cacheKey($url->short_code)));

        $this->getJson("/api/shorten/{$url->short_code}")->assertStatus(200);

        $this->assertTrue(Cache::has(Url::cacheKey($url->short_code)));
    }

    public function test_a_cached_lookup_is_served_without_hitting_the_database_again(): void
    {
        $url = Url::factory()->create();

        // Prime the cache
        $this->getJson("/api/shorten/{$url->short_code}")->assertStatus(200);

        // Delete the row directly (bypassing the controller/cache invalidation)
        // to prove the second read comes from cache, not the database.
        Url::where('id', $url->id)->delete();

        $response = $this->getJson("/api/shorten/{$url->short_code}");

        $response->assertStatus(200)
            ->assertJson(['shortCode' => $url->short_code]);
    }

    public function test_updating_a_url_invalidates_its_cache_entry(): void
    {
        $url = Url::factory()->create(['url' => 'https://example.com/original']);

        $this->getJson("/api/shorten/{$url->short_code}")->assertStatus(200);
        $this->assertTrue(Cache::has(Url::cacheKey($url->short_code)));

        $this->putJson("/api/shorten/{$url->short_code}", [
            'url' => 'https://example.com/updated',
        ])->assertStatus(200);

        $this->assertFalse(Cache::has(Url::cacheKey($url->short_code)));

        // Next read must reflect the update, not a stale cached value
        $this->getJson("/api/shorten/{$url->short_code}")
            ->assertJson(['url' => 'https://example.com/updated']);
    }

    public function test_deleting_a_url_invalidates_its_cache_entry(): void
    {
        $url = Url::factory()->create();

        $this->getJson("/api/shorten/{$url->short_code}")->assertStatus(200);
        $this->assertTrue(Cache::has(Url::cacheKey($url->short_code)));

        $this->deleteJson("/api/shorten/{$url->short_code}")->assertStatus(204);

        $this->assertFalse(Cache::has(Url::cacheKey($url->short_code)));
    }

    public function test_a_missing_short_code_is_never_cached(): void
    {
        $this->getJson('/api/shorten/doesNotExist')->assertStatus(404);

        $this->assertFalse(Cache::has(Url::cacheKey('doesNotExist')));
    }

    public function test_redirect_lookup_also_uses_the_cache(): void
    {
        $url = Url::factory()->create(['url' => 'https://example.com/target']);

        $this->get("/{$url->short_code}")->assertStatus(302);

        $this->assertTrue(Cache::has(Url::cacheKey($url->short_code)));
    }
}
