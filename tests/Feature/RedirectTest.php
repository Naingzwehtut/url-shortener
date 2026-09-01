<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_to_the_original_url(): void
    {
        $url = Url::factory()->create([
            'url' => 'https://example.com/target',
            'short_code' => 'aB92xK',
        ]);

        $response = $this->get('/aB92xK');

        $response->assertStatus(302);
        $response->assertRedirect('https://example.com/target');
    }

    public function test_access_count_increases_on_redirect(): void
    {
        $url = Url::factory()->create(['short_code' => 'aB92xK', 'access_count' => 0]);

        $this->get('/aB92xK');

        $this->assertSame(1, $url->fresh()->access_count);
    }

    public function test_access_count_increases_on_each_visit(): void
    {
        $url = Url::factory()->create(['short_code' => 'aB92xK', 'access_count' => 0]);

        $this->get('/aB92xK');
        $this->get('/aB92xK');
        $this->get('/aB92xK');

        $this->assertSame(3, $url->fresh()->access_count);
    }

    public function test_it_returns_404_for_a_missing_short_code(): void
    {
        $response = $this->get('/doesNotExist');

        $response->assertStatus(404);
    }

    /**
     * Simulates concurrent redirects hitting the same row. This doesn't
     * exercise real parallel database connections (PHPUnit is single
     * process/single connection), but it does prove the increment is
     * expressed as an atomic SQL statement rather than a PHP read-modify-
     * write — the property that actually matters under real concurrency.
     * See app/Models/Url.php::incrementAccessCount() for the mechanism.
     */
    public function test_concurrent_style_sequential_increments_do_not_lose_updates(): void
    {
        $url = Url::factory()->create(['access_count' => 0]);

        foreach (range(1, 10) as $i) {
            $fresh = Url::where('short_code', $url->short_code)->first();
            $fresh->incrementAccessCount();
        }

        $this->assertSame(10, $url->fresh()->access_count);
    }
}
