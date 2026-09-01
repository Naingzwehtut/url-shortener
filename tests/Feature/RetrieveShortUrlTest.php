<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrieveShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retrieves_an_existing_short_url(): void
    {
        $url = Url::factory()->create([
            'url' => 'https://example.com/target',
            'short_code' => 'aB92xK',
        ]);

        $response = $this->getJson("/api/shorten/{$url->short_code}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $url->id,
                'url' => 'https://example.com/target',
                'shortCode' => 'aB92xK',
            ]);
    }

    public function test_it_returns_404_for_a_missing_short_code(): void
    {
        $response = $this->getJson('/api/shorten/doesNotExist');

        $response->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
