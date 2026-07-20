<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Models\User;

/**
 * Record-level authorization for User records. Route middleware
 * (`permission:users.update`, `permission:users.delete` — see
 * Routes/api.php) gates the *capability*; a Policy gates the *record*, once
 * a specific target user is known. This is the base's canonical example of
 * that split — copy this pattern for any module whose actions need more
 * than "does the actor hold this permission".
 *
 * Auto-discovered by Laravel via the Models -> Policies namespace
 * convention (Gate::guessPolicyName replaces "\Models\" with "\Policies\"),
 * so nothing registers this class explicitly. Permission's Gate::before
 * hook only ever *allows* (see PermissionServiceProvider::registerGateHook)
 * and defers otherwise, so it steps aside cleanly for the record-specific
 * logic below.
 */
class UserPolicy
{
    /**
     * A user may always update their own profile — PROJECT_AUTH_DEFAULT_ROLE
     * grants no permissions by default, so without this, a freshly
     * registered user could never change their own name or password.
     */
    public function update(User $actor, User $subject): bool
    {
        return $actor->is($subject) || $actor->hasPermissionTo('users.update');
    }

    /**
     * Never allow self-deletion through this endpoint, even for an admin
     * holding users.delete: an admin (or an attacker riding a hijacked
     * session) locking the account out is a support incident, not a
     * feature. An account-deletion flow a user runs on themselves, if the
     * product needs one, deserves its own deliberately separate endpoint
     * (re-auth prompt, confirmation, etc.) — not this one.
     */
    public function delete(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return false;
        }

        return $actor->hasPermissionTo('users.delete');
    }
}
