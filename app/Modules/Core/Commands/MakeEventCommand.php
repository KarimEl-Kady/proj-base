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

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

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
        $modelImport = $modelName ? "\nuse {$this->namespace}\\Models\\{$modelName};" : '';
        $constructor = $modelName
            ? "\n\n    public function __construct(\n        public readonly {$modelName} \$".Str::camel($modelName)."\n    ) {}"
            : '';

        $broadcastMethod = '';
        if ($channels) {
            $channelsArray = array_map('trim', explode(',', $channels));
            $channelCalls = implode(",\n            ", array_map(fn ($c) => "new Channel('{$c}')", $channelsArray));
            $broadcastMethod = "\n    public function broadcastOn(): array\n    {\n        return [\n            {$channelCalls}\n        ];\n    }";
        }

        $content = $this->renderStub('event', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $name,
            '{{ modelImport }}' => $modelImport,
            '{{ constructor }}' => $constructor,
            '{{ broadcastMethod }}' => $broadcastMethod,
        ]);

        $this->write("Events/{$name}.php", $content);
        $this->info("Event [{$name}] created in [{$this->moduleName}] module.");
    }

    protected function makeListener(string $name): void
    {
        $content = $this->renderStub('listener', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$name}Listener",
            '{{ eventClass }}' => $name,
        ]);

        $this->write("Listeners/{$name}Listener.php", $content);
        $this->info("Listener [{$name}Listener] created in [{$this->moduleName}] module.");
    }

    protected function renderStub(string $name, array $replacements = []): string
    {
        $path = $this->stubsPath.'/'.$name.'.stub';
        $content = File::get($path);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function write(string $relativePath, string $content): void
    {
        $path = $this->modulePath.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->line("  Created: {$relativePath}");
    }
}