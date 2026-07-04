<?php

namespace App\Modules\Core\Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_returns_the_flat_status_shape(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'version',
            'checks' => ['database', 'cache', 'queue'],
        ]);

        // Deliberately not the success/message/data envelope.
        $response->assertJsonMissing(['success' => true]);
    }

    public function test_health_check_reports_healthy_when_all_checks_pass(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'healthy');
        $response->assertJsonPath('version', config('project.version'));
    }
}
