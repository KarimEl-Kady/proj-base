<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression guard: the User module ships no web/dashboard UI. An earlier
 * revision exposed unauthenticated /users web CRUD (create/update/delete
 * with no auth or permissions) — these routes must stay gone until a
 * project adds them back deliberately, protected, with real views.
 */
class UserWebSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_public_web_user_routes_exist(): void
    {
        $user = User::factory()->create();

        $this->get('/users')->assertNotFound();
        $this->get("/users/{$user->uuid}")->assertNotFound();
        $this->post('/users', [])->assertNotFound();
        $this->put("/users/{$user->uuid}", [])->assertNotFound();
        $this->delete("/users/{$user->uuid}")->assertNotFound();
    }

    public function test_no_dashboard_user_routes_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/dashboard/users')->assertNotFound();
        $this->delete("/dashboard/users/{$user->uuid}")->assertNotFound();
    }

    public function test_guests_on_auth_protected_web_routes_are_redirected_not_crashed(): void
    {
        // No named "login" route exists (Auth is API-only) — the guest
        // redirect fallback in bootstrap/app.php must send guests to "/"
        // instead of throwing RouteNotFoundException.
        Route::middleware(['web', 'auth'])
            ->get('/test/needs-auth', fn () => 'ok');

        $this->get('/test/needs-auth')->assertRedirect('/');
    }
}
