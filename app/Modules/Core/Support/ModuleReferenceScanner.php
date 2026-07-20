<?php

namespace App\Modules\Core\Support;

/**
 * Finds App\... references in a PHP file for module:boundaries, using
 * PHP's own tokenizer instead of a byte-level regex over the raw file.
 *
 * Two token kinds count as a reference:
 *  - T_NAME_QUALIFIED / T_NAME_FULLY_QUALIFIED: a real symbol reference —
 *    a `use` import, a type hint, `new X`, `X::class`, extends/implements,
 *    a catch type. This is what a byte-level regex can't distinguish from
 *    a docblock that merely *mentions* another module's class in passing;
 *    tokenizing means a comment is never one of these token kinds.
 *  - T_CONSTANT_ENCAPSED_STRING: a string literal. Kept deliberately,
 *    because this project's own generators build class names dynamically
 *    ("App\\Modules\\{$module}\\Models\\..."), and a literal module name
 *    inside a built string is real signal a pure symbol-reference scan
 *    would miss. A genuinely interpolated variable segment (the {$module}
 *    part itself) can't be resolved by any static tool — tokenizer or a
 *    full AST alike — that limitation is inherent, not specific to this
 *    implementation.
 */
class ModuleReferenceScanner
{
    protected const MODULE_PATTERN = '/App\\\\{1,2}Modules\\\\{1,2}([A-Za-z0-9]+)\\\\{1,2}/';

    protected const APP_NAMESPACE_PATTERN = '/\bApp\\\\{1,2}[A-Za-z0-9]/';

    /**
     * Distinct module names (the segment right after App\Modules\)
     * referenced by this file's real code or string literals.
     *
     * @return array<int, string>
     */
    public static function referencedModules(string $contents): array
    {
        $modules = [];

        foreach (static::relevantTokenTexts($contents) as $text) {
            if (preg_match_all(self::MODULE_PATTERN, $text, $matches) > 0) {
                array_push($modules, ...$matches[1]);
            }
        }

        return array_values(array_unique($modules));
    }

    /**
     * Whether this file's real code or string literals reference the App\
     * namespace at all — used by the local-package independence check
     * (packages must not import App\ classes, in any module).
     */
    public static function referencesAppNamespace(string $contents): bool
    {
        foreach (static::relevantTokenTexts($contents) as $text) {
            if (preg_match(self::APP_NAMESPACE_PATTERN, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected static function relevantTokenTexts(string $contents): array
    {
        $texts = [];

        foreach (token_get_all($contents) as $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_CONSTANT_ENCAPSED_STRING) {
                $texts[] = $text;
            }
        }

        return $texts;
    }
}
