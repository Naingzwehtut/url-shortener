<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // always reset the frozen clock between tests
        parent::tearDown();
    }

    public function test_a_redirect_logs_a_click(): void
    {
        $url = Url::factory()->create();

        $this->get("/{$url->short_code}", ['User-Agent' => 'PHPUnit/Test']);

        $this->assertDatabaseHas('url_clicks', [
            'url_id' => $url->id,
            'user_agent' => 'PHPUnit/Test',
        ]);
    }

    public function test_analytics_reports_total_clicks(): void
    {
        $url = Url::factory()->create();

        $this->get("/{$url->short_code}");
        $this->get("/{$url->short_code}");
        $this->get("/{$url->short_code}");

        $response = $this->getJson("/api/shorten/{$url->short_code}/analytics");

        $response->assertStatus(200)
            ->assertJson(['totalClicks' => 3]);
    }

    public function test_analytics_separates_clicks_today_from_older_clicks(): void
    {
        $url = Url::factory()->create();

        Carbon::setTestNow('2026-08-20 10:00:00');
        $this->get("/{$url->short_code}"); // an older click

        Carbon::setTestNow('2026-08-31 09:00:00');
        $this->get("/{$url->short_code}"); // today's click

        $response = $this->getJson("/api/shorten/{$url->short_code}/analytics");

        $response->assertJson([
            'totalClicks' => 2,
            'clicksToday' => 1,
        ]);
    }

    public function test_analytics_reports_last_accessed_time(): void
    {
        $url = Url::factory()->create();

        Carbon::setTestNow('2026-08-31 12:00:00');
        $this->get("/{$url->short_code}");

        $response = $this->getJson("/api/shorten/{$url->short_code}/analytics");

        $response->assertJson([
            'lastAccessedAt' => '2026-08-31T12:00:00+00:00',
        ]);
    }

    public function test_analytics_groups_clicks_by_date(): void
    {
        $url = Url::factory()->create();

        Carbon::setTestNow('2026-08-29 08:00:00');
        $this->get("/{$url->short_code}");

        Carbon::setTestNow('2026-08-30 09:00:00');
        $this->get("/{$url->short_code}");
        $this->get("/{$url->short_code}");

        $response = $this->getJson("/api/shorten/{$url->short_code}/analytics");

        $byDate = collect($response->json('clicksByDate'))->keyBy('date');

        $this->assertSame(1, $byDate['2026-08-29']['count']);
        $this->assertSame(2, $byDate['2026-08-30']['count']);
    }

    public function test_analytics_returns_404_for_a_missing_short_code(): void
    {
        $this->getJson('/api/shorten/doesNotExist/analytics')->assertStatus(404);
    }

    public function test_analytics_with_no_clicks_yet_returns_zeroes(): void
    {
        $url = Url::factory()->create();

        $response = $this->getJson("/api/shorten/{$url->short_code}/analytics");

        $response->assertJson([
            'totalClicks' => 0,
            'clicksToday' => 0,
            'clicksThisWeek' => 0,
            'lastAccessedAt' => null,
            'clicksByDate' => [],
        ]);
    }
}
