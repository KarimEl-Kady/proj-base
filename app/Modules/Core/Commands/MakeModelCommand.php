<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModelCommand extends Command
{
    protected $signature = 'make:module:model
                            {module : Module name}
                            {name : Model name}
                            {--fillable= : Comma-separated fillable fields}';

    protected $description = 'Create a model in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    public function handle(): int
    {
        $this->moduleName = Str::studly($this->argument('module'));
        $modelName = Str::studly($this->argument('name'));
        $fillable = $this->option('fillable');

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $tableName = Str::snake(Str::plural($modelName));
        $fillableArray = $fillable ? $this->parseFillable($fillable) : [];
        $fillableString = $fillableArray
            ? "['".implode("', '", $fillableArray)."']"
            : '[]';

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Models;

use App\\Modules\\Core\\Models\\Model;
use App\\Modules\\Core\\Traits\\HasTenantScope;
use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;

#[Fillable({$fillableString})]
class {$modelName} extends Model
{
    use HasTenantScope;

    protected \$table = '{$tableName}';

    protected function casts(): array
    {
        return [
            //
        ];
    }
}

PHP;

        $this->write("Models/{$modelName}.php", $content);
        $this->info("Model [{$modelName}] created in [{$this->moduleName}] module.");

        return self::SUCCESS;
    }

    protected function parseFillable(string $fields): array
    {
        return array_map('trim', explode(',', $fields));
    }

    protected function write(string $relativePath, string $content): void
    {
        $path = $this->modulePath.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->cleanContent($content));
        $this->line("  Created: {$relativePath}");
    }

    protected function cleanContent(string $content): string
    {
        $lines = explode("\n", $content);
        if (count($lines) <= 1) {
            return $content;
        }
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^(\s+)/', $line, $m)) {
                $minIndent = min($minIndent, strlen($m[1]));
            } else {
                $minIndent = 0;
                break;
            }
        }
        if ($minIndent > 0 && $minIndent < PHP_INT_MAX) {
            foreach ($lines as &$line) {
                if ($line !== '') {
                    $line = substr($line, $minIndent);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
