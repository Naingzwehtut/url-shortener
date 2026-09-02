<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateShortUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_short_url(): void
    {
        $response = $this->postJson('/api/shorten', [
            'url' => 'https://www.example.com/some/very/long/url',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'url', 'shortCode', 'createdAt', 'updatedAt'])
            ->assertJson(['url' => 'https://www.example.com/some/very/long/url']);

        $this->assertDatabaseHas('urls', [
            'url' => 'https://www.example.com/some/very/long/url',
        ]);
    }

    public function test_generated_short_code_is_six_characters_and_alphanumeric(): void
    {
        $response = $this->postJson('/api/shorten', ['url' => 'https://example.com']);

        $shortCode = $response->json('shortCode');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{6}$/', $shortCode);
    }

    public function test_short_codes_are_unique_across_multiple_creations(): void
    {
        $codes = [];

        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/shorten', [
                'url' => "https://example.com/page-{$i}",
            ]);

            $codes[] = $response->json('shortCode');
        }

        $this->assertCount(20, array_unique($codes));
    }

    public function test_it_rejects_a_missing_url(): void
    {
        $response = $this->postJson('/api/shorten', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_it_rejects_invalid_urls(string $invalidUrl): void
    {
        $response = $this->postJson('/api/shorten', ['url' => $invalidUrl]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'plain word' => ['hello'],
            'no scheme' => ['example'],
            'looks like a slug' => ['not-a-url'],
        ];
    }

    public function test_it_accepts_urls_with_query_strings(): void
    {
        $response = $this->postJson('/api/shorten', [
            'url' => 'https://example.com/products?id=123&ref=abc',
        ]);

        $response->assertStatus(201);
    }

    public function test_it_accepts_very_long_urls_up_to_the_limit(): void
    {
        $longUrl = 'https://example.com/'.str_repeat('a', 2000);

        $response = $this->postJson('/api/shorten', ['url' => $longUrl]);

        $response->assertStatus(201);
        $this->assertSame($longUrl, $response->json('url'));
    }

    public function test_it_rejects_urls_exceeding_the_max_length(): void
    {
        $tooLongUrl = 'https://example.com/'.str_repeat('a', 2100);

        $response = $this->postJson('/api/shorten', ['url' => $tooLongUrl]);

        $response->assertStatus(422);
    }
}
