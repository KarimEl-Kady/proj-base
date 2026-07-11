<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate a fresh user for auth:sanctum-protected endpoints.
     *
     * Resolves the model from auth config instead of importing it so module
     * tests can call this without creating a cross-module dependency
     * (module:boundaries scans test files too).
     */
    protected function actingAsUser(): Authenticatable
    {
        $model = config('auth.providers.users.model');

        $user = $model::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
