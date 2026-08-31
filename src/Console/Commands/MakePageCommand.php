<?php

namespace Invue\Panels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Invue\Panels\Panel;
use Invue\Panels\PanelManager;
use RuntimeException;

/**
 * The "blank canvas" counterpart to make:invue-resource — no model, no
 * CRUD, just a real route plus an empty .vue page that already shows up in
 * the panel sidebar with a default icon. For anything that isn't a
 * per-record CRUD screen: a dashboard-style report, a settings form, a
 * one-off tool page.
 */
class MakePageCommand extends Command
{
    protected $signature = 'make:invue-page
        {name : The page name, e.g. Reports}
        {--panel= : The panel id to generate into (defaults to the only registered panel)}
        {--icon= : Lucide icon name for the sidebar entry (defaults to Page\'s own default, "file")}
        {--force : Overwrite files that already exist}';

    protected $description = 'Scaffold a blank custom Invue page — a real route + Vue page, already showing up in the panel sidebar';

    protected Filesystem $files;

    public function handle(Filesystem $files, PanelManager $manager): int
    {
        $this->files = $files;

        $panel = $this->resolvePanel($manager);

        if ($panel === null) {
            return self::FAILURE;
        }

        $name = Str::studly($this->argument('name'));
        $pageClass = "{$name}Page";
        $slug = Str::kebab($name);

        $targets = [
            'page' => $panel->getPageClassesDirectory()."/{$pageClass}.php",
            'vue' => $panel->getPagesDirectory()."/{$name}.vue",
        ];

        if (! $this->option('force')) {
            $existing = array_filter($targets, fn (string $path) => $this->files->exists($path));

            if ($existing !== []) {
                $this->components->error('These files already exist (pass --force to overwrite):');
                foreach ($existing as $path) {
                    $this->line('  '.$this->relative($path));
                }

                return self::FAILURE;
            }
        }

        $this->writePage($panel, $targets['page'], $pageClass, $slug);
        $this->writeVuePage($targets['vue'], Str::headline($name));

        $this->components->info("Invue page [{$name}] scaffolded into panel [{$panel->getId()}]:");
        foreach ($targets as $path) {
            $this->line('  '.$this->relative($path));
        }
        $this->line('');
        $this->line("Visit /{$panel->getPath()}/{$slug} once you're logged in — it's already in the sidebar, no route wiring needed, the panel discovers this page by directory convention.");

        return self::SUCCESS;
    }

    protected function resolvePanel(PanelManager $manager): ?Panel
    {
        try {
            $panelId = $this->option('panel');

            return $panelId !== null ? $manager->get($panelId) : $manager->sole();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return null;
        }
    }

    protected function writePage(Panel $panel, string $path, string $pageClass, string $slug): void
    {
        $icon = $this->option('icon');

        $stub = strtr($this->stub('page'), [
            '{{ pageNamespace }}' => $panel->getPageClassesNamespace(),
            '{{ pageClass }}' => $pageClass,
            '{{ slug }}' => $slug,
            '{{ iconLine }}' => $icon !== null ? "    protected static ?string \$navigationIcon = '{$icon}';\n" : '',
        ]);

        $this->put($path, $stub);
    }

    protected function writeVuePage(string $path, string $label): void
    {
        $stub = str_replace('%%NAVIGATION_LABEL%%', $label, $this->stub('page.vue'));

        $this->put($path, $stub);
    }

    protected function stub(string $name): string
    {
        return $this->files->get(__DIR__."/../../../stubs/{$name}.stub");
    }

    protected function put(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }

    protected function relative(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
