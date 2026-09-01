<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('shorten');
    }

    public function test_requests_within_the_limit_succeed(): void
    {
        config(['url_shortener.rate_limit_per_minute' => 3]);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/shorten', ['url' => "https://example.com/{$i}"])
                ->assertStatus(201);
        }
    }

    public function test_exceeding_the_limit_returns_429_with_a_consistent_error_shape(): void
    {
        config(['url_shortener.rate_limit_per_minute' => 2]);

        $this->postJson('/api/shorten', ['url' => 'https://example.com/1'])->assertStatus(201);
        $this->postJson('/api/shorten', ['url' => 'https://example.com/2'])->assertStatus(201);

        $response = $this->postJson('/api/shorten', ['url' => 'https://example.com/3']);

        $response->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_the_limit_is_scoped_to_the_shorten_endpoint_only(): void
    {
        config(['url_shortener.rate_limit_per_minute' => 1]);

        $create = $this->postJson('/api/shorten', ['url' => 'https://example.com']);
        $create->assertStatus(201);

        $shortCode = $create->json('shortCode');

        // Retrieval isn't throttled by this limiter — should not 429
        $this->getJson("/api/shorten/{$shortCode}")->assertStatus(200);
    }
}
