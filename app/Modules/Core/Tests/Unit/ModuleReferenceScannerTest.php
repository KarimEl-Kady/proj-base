<?php

namespace App\Modules\Core\Tests\Unit;

use App\Modules\Core\Support\ModuleReferenceScanner;
use Tests\TestCase;

class ModuleReferenceScannerTest extends TestCase
{
    public function test_real_references_are_found_via_namespace_use_typehint_and_string(): void
    {
        $contents = <<<'PHP'
        <?php

        namespace App\Modules\Auth\Services;

        use App\Modules\User\Models\User;

        class Foo
        {
            public function bar(\App\Modules\Geo\Models\City $c): void
            {
                $hardcoded = "App\\Modules\\Payment\\Models\\Invoice";

                return User::class;
            }
        }
        PHP;

        $modules = ModuleReferenceScanner::referencedModules($contents);

        $this->assertEqualsCanonicalizing(['Auth', 'User', 'Geo', 'Payment'], $modules);
    }

    public function test_a_module_name_mentioned_only_in_a_comment_or_docblock_does_not_count(): void
    {
        $contents = <<<'PHP'
        <?php

        namespace App\Modules\Auth\Services;

        class Foo
        {
            // This mentions App\Modules\Country\Models\Country but is not a real dependency.
            /** @see App\Modules\Country\Models\Country */
            public function bar(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Auth'], ModuleReferenceScanner::referencedModules($contents));
    }

    public function test_dynamically_interpolated_module_names_cannot_be_resolved_statically(): void
    {
        // Documented limitation, not a bug: a variable segment can't be
        // resolved without executing the code. No static tool — this one
        // or a full AST — can see through it.
        $contents = <<<'PHP'
        <?php

        namespace App\Modules\Auth\Services;

        class Foo
        {
            public function bar(string $module): string
            {
                return "App\\Modules\\{$module}\\Models\\Foo";
            }
        }
        PHP;

        $this->assertSame(['Auth'], ModuleReferenceScanner::referencedModules($contents));
    }

    public function test_references_app_namespace_ignores_comments_but_catches_use_and_strings(): void
    {
        $commentOnly = <<<'PHP'
        <?php

        namespace Local\Something;

        class Foo
        {
            // App\Models\Tenant is mentioned here only, not imported.
        }
        PHP;

        $this->assertFalse(ModuleReferenceScanner::referencesAppNamespace($commentOnly));

        $realImport = <<<'PHP'
        <?php

        namespace Local\Something;

        use App\Models\Tenant;

        class Foo
        {
        }
        PHP;

        $this->assertTrue(ModuleReferenceScanner::referencesAppNamespace($realImport));

        $stringOnly = <<<'PHP'
        <?php

        namespace Local\Something;

        class Foo
        {
            public function bar(): string
            {
                return "App\\Models\\Tenant";
            }
        }
        PHP;

        $this->assertTrue(ModuleReferenceScanner::referencesAppNamespace($stringOnly));
    }
}
