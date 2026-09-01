<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_statistics_for_a_short_url(): void
    {
        $url = Url::factory()->create(['access_count' => 41]);

        $response = $this->getJson("/api/shorten/{$url->short_code}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'url', 'shortCode', 'createdAt', 'updatedAt', 'accessCount'])
            ->assertJson(['accessCount' => 41]);
    }

    public function test_access_count_reflects_actual_redirects(): void
    {
        $url = Url::factory()->create(['access_count' => 0]);

        $this->get("/{$url->short_code}");
        $this->get("/{$url->short_code}");

        $response = $this->getJson("/api/shorten/{$url->short_code}/stats");

        $response->assertJson(['accessCount' => 2]);
    }

    public function test_it_returns_404_for_a_missing_short_code(): void
    {
        $response = $this->getJson('/api/shorten/doesNotExist/stats');

        $response->assertStatus(404);
    }
}
