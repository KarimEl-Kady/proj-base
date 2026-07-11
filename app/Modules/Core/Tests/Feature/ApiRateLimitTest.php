<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The project-wide API rate limit: every route in the "api" group is
 * throttled by RateLimiter::for('api') reading project.api.rate_limit
 * (requests per minute, keyed per user or IP).
 */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_over_the_limit_get_429(): void
    {
        config(['project.api.rate_limit' => 2]);

        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/health')->assertOk();

        $this->getJson('/api/health')
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_rate_limit_headers_are_exposed(): void
    {
        config(['project.api.rate_limit' => 5]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 5)
            ->assertHeader('X-RateLimit-Remaining', 4);
    }
}
