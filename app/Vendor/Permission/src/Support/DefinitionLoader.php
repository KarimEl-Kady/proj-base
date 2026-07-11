<?php

namespace Local\Permission\Support;

/**
 * Collects the project's declarative roles/permissions from every source:
 *
 * - config('permission.definitions')       — project-wide roles + extras
 * - config('permission.definition_paths')  — glob patterns (relative to
 *   base_path) of files each returning ['permissions' => [], 'roles' => []],
 *   so a module can own the permissions of the resource it ships instead of
 *   piling everything into one central array.
 *
 * Same-named roles from different sources have their permission lists
 * unioned; a '*' anywhere in a role's list keeps its "every defined
 * permission" meaning. permission:seed consumes the merged result.
 */
class DefinitionLoader
{
    /**
     * Every defined permission name, merged and de-duplicated.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        $permissions = config('permission.definitions.permissions', []);

        foreach ($this->fileDefinitions() as $definition) {
            $permissions = array_merge($permissions, $definition['permissions'] ?? []);
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Every defined role with its (merged) permission list.
     *
     * @return array<string, array<int, string>>
     */
    public function roles(): array
    {
        $roles = config('permission.definitions.roles', []);

        foreach ($this->fileDefinitions() as $definition) {
            foreach ($definition['roles'] ?? [] as $role => $permissions) {
                $roles[$role] = array_values(array_unique(
                    array_merge($roles[$role] ?? [], $permissions)
                ));
            }
        }

        return $roles;
    }

    /**
     * Definition files matched by the configured glob patterns.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach (config('permission.definition_paths', []) as $pattern) {
            $absolute = str_starts_with($pattern, DIRECTORY_SEPARATOR)
                ? $pattern
                : base_path($pattern);

            $paths = array_merge($paths, glob($absolute) ?: []);
        }

        return $paths;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fileDefinitions(): array
    {
        $definitions = [];

        foreach ($this->paths() as $path) {
            $definition = require $path;

            if (is_array($definition)) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }
}
