<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_an_existing_short_url(): void
    {
        $url = Url::factory()->create();

        $response = $this->deleteJson("/api/shorten/{$url->short_code}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('urls', ['id' => $url->id]);
    }

    public function test_deleted_url_can_no_longer_be_retrieved(): void
    {
        $url = Url::factory()->create();

        $this->deleteJson("/api/shorten/{$url->short_code}");

        $this->getJson("/api/shorten/{$url->short_code}")
            ->assertStatus(404);
    }

    public function test_it_returns_404_when_deleting_a_missing_short_code(): void
    {
        $response = $this->deleteJson('/api/shorten/doesNotExist');

        $response->assertStatus(404);
    }
}
