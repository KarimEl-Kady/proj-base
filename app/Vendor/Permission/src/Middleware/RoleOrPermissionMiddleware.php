<?php

namespace Local\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Local\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role_or_permission:admin|users.update') — passes if
 * the user has ANY of the given names, whether they resolve as a role or a
 * permission. Handy for a single check that covers "admins, or anyone
 * specifically granted this permission."
 */
class RoleOrPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $names, ?string $guard = null): Response
    {
        $names = explode('|', $names);
        $user = $request->user($guard);

        $passes = $user !== null
            && ((method_exists($user, 'hasAnyRole') && $user->hasAnyRole($names))
                || (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($names)));

        if (! $passes) {
            throw UnauthorizedException::forPermissions($names);
        }

        return $next($request);
    }
}
