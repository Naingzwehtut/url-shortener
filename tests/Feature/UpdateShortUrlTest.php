<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_target_url(): void
    {
        $url = Url::factory()->create(['short_code' => 'aB92xK']);

        $response = $this->putJson("/api/shorten/{$url->short_code}", [
            'url' => 'https://www.example.com/updated-url',
        ]);

        $response->assertStatus(200)
            ->assertJson(['url' => 'https://www.example.com/updated-url']);
    }

    public function test_the_short_code_never_changes_on_update(): void
    {
        $url = Url::factory()->create(['short_code' => 'aB92xK']);

        $response = $this->putJson("/api/shorten/{$url->short_code}", [
            'url' => 'https://www.example.com/updated-url',
        ]);

        $response->assertJson(['shortCode' => 'aB92xK']);
        $this->assertSame('aB92xK', $url->fresh()->short_code);
    }

    public function test_it_rejects_an_invalid_url_on_update(): void
    {
        $url = Url::factory()->create();

        $response = $this->putJson("/api/shorten/{$url->short_code}", [
            'url' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_it_returns_404_when_updating_a_missing_short_code(): void
    {
        $response = $this->putJson('/api/shorten/doesNotExist', [
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(404);
    }
}
