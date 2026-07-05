<?php

namespace Local\Permission\Traits;

/**
 * Convenience trait for models that need both — the common case (e.g. the
 * User model). Add HasRoles or HasPermissions individually if a model only
 * needs one side.
 */
trait HasRolesAndPermissions
{
    use HasPermissions;
    use HasRoles;
}
