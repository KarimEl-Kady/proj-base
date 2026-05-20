<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeEventCommand extends Command
{
    protected $signature = 'make:module:event
                            {module : Module name}
                            {name : Event name}
                            {--model= : Associated model for the event}
                            {--channels= : Broadcast channels (comma-separated)}';

    protected $description = 'Create an event and listener pair in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    public function handle(): int
    {
        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model');
        $channels = $this->option('channels');

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $this->makeEvent($name, $modelName, $channels);
        $this->makeListener($name);

        return self::SUCCESS;
    }

    protected function makeEvent(string $name, ?string $modelName, ?string $channels): void
    {
        $eventClass = $name;
        $listenerClass = "{$name}Listener";
        $channelsArray = $channels ? array_map('trim', explode(',', $channels)) : [];

        $uses = $modelName ? "\nuse {$this->namespace}\\Models\\{$modelName};" : '';
        $constructor = $modelName
            ? <<<PHP

    public function __construct(
        public readonly {$modelName} \${Str::camel($modelName)}
    ) {}
PHP
            : '';

        $channelMethods = '';
        if (! empty($channelsArray)) {
            $channelMethods = "\n    public function broadcastOn(): array\n    {\n        return [\n            ".implode(",\n            ", array_map(fn ($c) => "new Channel('{$c}')", $channelsArray))."\n        ];\n    }";
        }

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Events{$uses}

use Illuminate\\Broadcasting\\Channel;
use Illuminate\\Broadcasting\\InteractsWithSockets;
use Illuminate\\Contracts\\Broadcasting\\ShouldBroadcast;
use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

class {$eventClass} implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    {$constructor}{$channelMethods}
}

PHP;

        $this->write("Events/{$eventClass}.php", $content);
        $this->info("Event [{$eventClass}] created in [{$this->moduleName}] module.");
    }

    protected function makeListener(string $name): void
    {
        $eventClass = $name;
        $listenerClass = "{$name}Listener";

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Listeners;

use {$this->namespace}\\Events\\{$eventClass};
use Illuminate\\Contracts\\Queue\\ShouldQueue;

class {$listenerClass} implements ShouldQueue
{
    public function handle({$eventClass} \$event): void
    {
        //
    }
}

PHP;

        $this->write("Listeners/{$listenerClass}.php", $content);
        $this->info("Listener [{$listenerClass}] created in [{$this->moduleName}] module.");
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
