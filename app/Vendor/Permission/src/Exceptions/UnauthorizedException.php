<?php

namespace Local\Permission\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Extends Laravel's own AuthorizationException so the host app's exception
 * handler renders it exactly like any other 403 — no wiring needed in the
 * consuming project (Core\Exceptions\Handler already matches on
 * AuthorizationException).
 */
class UnauthorizedException extends AuthorizationException
{
    /**
     * @param  array<int, string>  $roles
     */
    public static function forRoles(array $roles): self
    {
        return new self('User does not have any of the necessary roles: '.implode(', ', $roles));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function forPermissions(array $permissions): self
    {
        return new self('User does not have any of the necessary permissions: '.implode(', ', $permissions));
    }
}
