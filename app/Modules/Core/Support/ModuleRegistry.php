<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\File;

/**
 * Reads and writes the module registry at config/project_modules.php —
 * the single source of truth for active modules. Always reads the file
 * directly so commands see fresh state even when config is cached.
 */
class ModuleRegistry
{
    public static function path(): string
    {
        return base_path('config/project_modules.php');
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        if (! File::exists(static::path())) {
            return [];
        }

        $modules = require static::path();

        return is_array($modules) ? $modules : [];
    }

    /**
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        return array_keys(array_filter(static::all()));
    }

    public static function has(string $module): bool
    {
        return array_key_exists($module, static::all());
    }

    public static function isEnabled(string $module): bool
    {
        return static::all()[$module] ?? false;
    }

    public static function set(string $module, bool $enabled): void
    {
        $modules = static::all();
        $modules[$module] = $enabled;

        static::write($modules);
    }

    public static function remove(string $module): void
    {
        $modules = static::all();
        unset($modules[$module]);

        static::write($modules);
    }

    /**
     * @param  array<string, bool>  $modules
     */
    protected static function write(array $modules): void
    {
        $lines = '';
        foreach ($modules as $name => $enabled) {
            $value = $enabled ? 'true' : 'false';
            $lines .= "    '{$name}' => {$value},\n";
        }

        $content = <<<PHP
        <?php

        /*
        |--------------------------------------------------------------------------
        | Module Registry
        |--------------------------------------------------------------------------
        |
        | Single source of truth for which HMVC modules are active. Toggle the
        | boolean by hand or via artisan: module:enable, module:disable,
        | module:delete. New modules created with make:module are registered
        | here automatically. Keep the simple `'Name' => bool` format — the
        | file is rewritten by those commands.
        |
        */

        return [
        {$lines}];

        PHP;

        File::put(static::path(), $content);
    }
}
