<?php

namespace App\Modules\Core\Tests\Feature;

use Tests\TestCase;

class PlatformSafetyTest extends TestCase
{
    public function test_request_id_is_generated_and_valid_incoming_id_is_preserved(): void
    {
        $generated = $this->getJson('/api/health/live')->headers->get('X-Request-ID');
        $this->assertNotNull($generated);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $generated);

        $this->withHeader('X-Request-ID', 'request-12345678')
            ->getJson('/api/health/live')
            ->assertHeader('X-Request-ID', 'request-12345678');
    }

    public function test_invalid_configuration_fails_validation(): void
    {
        config(['project.pagination.unpaginated_cap' => -1]);

        $this->artisan('project:validate')
            ->expectsOutputToContain('project.pagination.unpaginated_cap')
            ->assertFailed();
    }
}
