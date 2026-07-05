<?php

namespace Local\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Local\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:users.create')
 * or ->middleware('permission:users.create|users.update') (pipe = any-of).
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions, ?string $guard = null): Response
    {
        $permissions = explode('|', $permissions);
        $user = $request->user($guard);

        if ($user === null || ! method_exists($user, 'hasAnyPermission') || ! $user->hasAnyPermission($permissions)) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);
    }
}
