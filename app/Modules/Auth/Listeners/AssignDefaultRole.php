<?php

namespace App\Modules\Auth\Listeners;

use App\Modules\Auth\Events\UserRegistered;

/**
 * Gives every newly registered user the role named in
 * project.auth.default_role (PROJECT_AUTH_DEFAULT_ROLE) — a no-op when the
 * config is null (the default) or when the user model doesn't use
 * local/permission's HasRoles trait.
 *
 * Auto-discovered from this module's Listeners directory (see
 * bootstrap/app.php) via the Registered type-hint below — no manual
 * registration anywhere.
 */
class AssignDefaultRole
{
    public function handle(UserRegistered $event): void
    {
        $role = config('project.auth.default_role');

        if ($role === null || $role === '') {
            return;
        }

        $event->user->assignRole($role);
    }
}
