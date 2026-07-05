<?php

namespace Local\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Local\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or ->middleware('role:admin|manager')
 * (pipe-separated = any-of).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles, ?string $guard = null): Response
    {
        $roles = explode('|', $roles);
        $user = $request->user($guard);

        if ($user === null || ! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole($roles)) {
            throw UnauthorizedException::forRoles($roles);
        }

        return $next($request);
    }
}
