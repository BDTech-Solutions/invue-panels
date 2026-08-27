<?php

namespace Invue\Panels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePanelCommand extends Command
{
    protected $signature = 'make:invue-panel
        {name : The panel name, e.g. Admin}
        {--path= : URL path prefix, defaults to a kebab-case of the name}';

    protected $description = 'Scaffold a new Invue panel provider';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $id = Str::kebab($name);
        $path = ltrim((string) ($this->option('path') ?: $id), '/');

        $class = "{$name}PanelProvider";
        $providerPath = app_path("Providers/{$class}.php");

        if ($files->exists($providerPath)) {
            $this->components->error("App\\Providers\\{$class} already exists.");

            return self::FAILURE;
        }

        $stub = $files->get(__DIR__.'/../../../stubs/panel-provider.stub');

        $stub = strtr($stub, [
            '{{ class }}' => $class,
            '{{ id }}' => $id,
            '{{ path }}' => $path,
        ]);

        $files->ensureDirectoryExists(dirname($providerPath));
        $files->put($providerPath, $stub);

        $this->registerProvider($files, "App\\Providers\\{$class}");

        $this->components->info("Invue panel provider created: app/Providers/{$class}.php");
        $this->line('');
        $this->line("  Panel id: <comment>{$id}</comment>   URL prefix: <comment>/{$path}</comment>");
        $this->line('');
        $this->line('Add a Resource to this panel with:');
        $this->line("  <fg=green>php artisan make:invue-resource</> <ModelName> <fg=gray>--panel={$id}</>");

        return self::SUCCESS;
    }

    protected function registerProvider(Filesystem $files, string $providerClass): void
    {
        $bootstrapProviders = base_path('bootstrap/providers.php');

        if (! $files->exists($bootstrapProviders)) {
            $this->components->warn('bootstrap/providers.php not found — register '.$providerClass.' manually.');

            return;
        }

        $contents = $files->get($bootstrapProviders);

        if (str_contains($contents, $providerClass.'::class')) {
            return;
        }

        $updated = preg_replace(
            '/return \[\n/',
            "return [\n    {$providerClass}::class,\n",
            $contents,
            limit: 1,
        );

        if ($updated === null || $updated === $contents) {
            $this->components->warn('Could not auto-register the provider in bootstrap/providers.php — add '.$providerClass.'::class manually.');

            return;
        }

        $files->put($bootstrapProviders, $updated);
    }
}
