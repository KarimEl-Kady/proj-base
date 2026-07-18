<?php

namespace App\Modules\Core\Tests\Feature;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ApiDisabledTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        putenv('PROJECT_API_ENABLED=false');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('PROJECT_API_ENABLED');
    }

    public function test_business_api_routes_are_not_registered_but_health_remains_available(): void
    {
        $this->getJson('/api/v1/countries')->assertNotFound();
        $this->postJson('/api/v1/auth/login')->assertNotFound();
        $this->getJson('/api/health/live')->assertOk();
    }
}
