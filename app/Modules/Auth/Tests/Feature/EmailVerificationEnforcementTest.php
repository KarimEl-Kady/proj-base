<?php

namespace App\Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The `verified.feature` middleware (Auth module) makes the base's email
 * verification posture explicit: verification emails are informational
 * until a route carries this middleware, and the middleware only enforces
 * while PROJECT_FEATURE_EMAIL_VERIFICATION is on.
 */
class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'verified.feature'])
            ->get('/api/test/verified-probe', fn () => response()->json(['ok' => true]));
    }

    protected function actingAsUserVerified(bool $verified): void
    {
        $model = config('auth.providers.users.model');

        Sanctum::actingAs($model::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]));
    }

    public function test_unverified_users_pass_while_the_feature_is_off(): void
    {
        config(['project.features.email_verification' => false]);
        $this->actingAsUserVerified(false);

        $this->getJson('/api/test/verified-probe')->assertOk();
    }

    public function test_unverified_users_are_rejected_while_the_feature_is_on(): void
    {
        config(['project.features.email_verification' => true]);
        $this->actingAsUserVerified(false);

        $this->getJson('/api/test/verified-probe')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your email address is not verified.');
    }

    public function test_verified_users_pass_while_the_feature_is_on(): void
    {
        config(['project.features.email_verification' => true]);
        $this->actingAsUserVerified(true);

        $this->getJson('/api/test/verified-probe')->assertOk();
    }

    public function test_guests_hit_the_auth_gate_first(): void
    {
        config(['project.features.email_verification' => true]);

        $this->getJson('/api/test/verified-probe')->assertUnauthorized();
    }
}
