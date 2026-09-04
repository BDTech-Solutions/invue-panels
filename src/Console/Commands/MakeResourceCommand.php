<?php

namespace Invue\Panels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Invue\Infolists\InfolistsServiceProvider;
use Invue\Notifications\NotificationsServiceProvider;
use Invue\Panels\Console\Support\ColumnInference;
use Invue\Panels\Console\Support\FieldDescriptor;
use Invue\Panels\Console\Support\FieldRenderer;
use Invue\Panels\Panel;
use Invue\Panels\PanelManager;
use RuntimeException;

class MakeResourceCommand extends Command
{
    protected $signature = 'make:invue-resource
        {name : The resource name, e.g. Post — also the default model name}
        {--panel= : The panel id to generate into (defaults to the only registered panel)}
        {--model= : The model class, if it differs from {name} — a bare name resolves under App\Models, or pass a fully-qualified class}
        {--view : Also generate a read-only Show (Infolist) page — requires invue/infolists}
        {--force : Overwrite files that already exist}';

    protected $description = 'Scaffold a full Invue CRUD resource (Resource, Controller, FormRequest, Vue pages) from an already-migrated model table';

    protected Filesystem $files;

    public function handle(Filesystem $files, PanelManager $manager): int
    {
        $this->files = $files;

        $panel = $this->resolvePanel($manager);

        if ($panel === null) {
            return self::FAILURE;
        }

        $modelClass = $this->resolveModelClass();

        if ($modelClass === null) {
            return self::FAILURE;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $this->components->error("Table [{$table}] for {$modelClass} doesn't exist. Migrate it first (`php artisan migrate`), then re-run this command.");

            return self::FAILURE;
        }

        $fields = ColumnInference::forTable($table, $model->getKeyName());
        $wantsView = $this->resolveWantsView();

        $modelBasename = class_basename($modelClass);
        $resourceClass = "{$modelBasename}Resource";
        $controllerClass = "{$modelBasename}Controller";
        $requestClass = "{$modelBasename}Request";
        $pluralStudly = Str::pluralStudly($modelBasename);
        $slug = Str::kebab($pluralStudly);
        $tableProp = Str::camel($pluralStudly);
        $modelVariable = Str::camel(Str::singular($modelBasename));
        $pageBase = $panel->getPagesNamespace().'/'.$pluralStudly;
        $routeNamePrefix = $panel->getRouteNamePrefix().$slug;
        // Plain URLs for the generated Vue pages, not named-route lookups —
        // the client has no Ziggy/Wayfinder wired by default (invue:install
        // never installs either), so a client-side route('name') call would
        // throw at runtime. The controller stub still uses real route names
        // via to_route() — that's server-side, unaffected.
        $baseUrl = '/'.trim($panel->getPath(), '/').'/'.$slug;

        $targets = [
            'resource' => $panel->getResourcesDirectory()."/{$resourceClass}.php",
            'controller' => $panel->getControllersDirectory()."/{$controllerClass}.php",
            'request' => $panel->getRequestsDirectory()."/{$requestClass}.php",
            'index' => $panel->getPagesDirectory()."/{$pluralStudly}/Index.vue",
            'create' => $panel->getPagesDirectory()."/{$pluralStudly}/Create.vue",
            'edit' => $panel->getPagesDirectory()."/{$pluralStudly}/Edit.vue",
        ];

        if ($wantsView) {
            $targets['show'] = $panel->getPagesDirectory()."/{$pluralStudly}/Show.vue";
        }

        if (! $this->option('force')) {
            $existing = array_filter($targets, fn (string $path) => $this->files->exists($path));

            if ($existing !== []) {
                $this->components->error('These files already exist (pass --force to overwrite):');
                foreach ($existing as $path) {
                    $this->line("  {$path}");
                }

                return self::FAILURE;
            }
        }

        $this->writeResource($panel, $targets['resource'], $resourceClass, $modelClass, $wantsView);
        $this->writeRequest($panel, $targets['request'], $requestClass, $fields);
        $this->writeController($panel, $targets['controller'], [
            'controllerClass' => $controllerClass,
            'modelClass' => $modelClass,
            'requestClass' => $requestClass,
            'requestsNamespace' => $panel->getRequestsNamespace(),
            'pageBase' => $pageBase,
            'tableProp' => $tableProp,
            'modelVariable' => $modelVariable,
            'indexRouteName' => "{$routeNamePrefix}.index",
            'fields' => $fields,
            'wantsView' => $wantsView,
        ]);
        $this->writeIndexPage($targets['index'], [
            'tableProp' => $tableProp,
            'navigationLabel' => Str::plural(Str::headline($modelBasename)),
            'modelLabel' => Str::headline($modelBasename),
            'createUrl' => "{$baseUrl}/create",
            'editUrlBase' => $baseUrl,
            'primaryKey' => $model->getKeyName(),
            'fields' => $fields,
        ]);
        $this->writeCreatePage($targets['create'], [
            'modelLabel' => Str::headline($modelBasename),
            'navigationLabel' => Str::plural(Str::headline($modelBasename)),
            'indexUrl' => $baseUrl,
            'storeUrl' => $baseUrl,
            'fields' => $fields,
        ]);
        $this->writeEditPage($targets['edit'], [
            'modelLabel' => Str::headline($modelBasename),
            'navigationLabel' => Str::plural(Str::headline($modelBasename)),
            'indexUrl' => $baseUrl,
            'modelVariable' => $modelVariable,
            'primaryKey' => $model->getKeyName(),
            'updateUrlBase' => $baseUrl,
            'fields' => $fields,
        ]);

        if ($wantsView) {
            $this->writeShowPage($targets['show'], [
                'modelLabel' => Str::headline($modelBasename),
                'navigationLabel' => Str::plural(Str::headline($modelBasename)),
                'indexUrl' => $baseUrl,
                'editUrlBase' => $baseUrl,
                'primaryKey' => $model->getKeyName(),
                'modelVariable' => $modelVariable,
                'fields' => $fields,
            ]);
        }

        $this->components->info("Invue resource [{$modelBasename}] scaffolded into panel [{$panel->getId()}] from ".count($fields).' inferred field(s):');
        foreach ($targets as $path) {
            $this->line('  '.str_replace(base_path().'/', '', $path));
        }
        $this->line('');
        $this->line("Visit /{$panel->getPath()}/{$slug} once you're logged in — no route wiring needed, the panel discovers this Resource by directory convention.");

        if (! $wantsView && class_exists(InfolistsServiceProvider::class)) {
            $this->line('Re-run with --view to also scaffold a read-only Show (Infolist) page.');
        }

        return self::SUCCESS;
    }

    /**
     * `--view` short-circuits the prompt; otherwise ask, defaulting to no —
     * matches Filament's own posture of List/Create/Edit by default, View
     * as an opt-in extra. Silently false with no prompt at all when
     * invue/infolists isn't installed (nothing to generate into) or the
     * run is non-interactive (CI, --no-interaction) — never block on a
     * question nobody can answer.
     */
    protected function resolveWantsView(): bool
    {
        if (! class_exists(InfolistsServiceProvider::class)) {
            if ($this->option('view')) {
                $this->components->warn('--view requires invue/infolists — install it first (`composer require invue/infolists`), then re-run.');
            }

            return false;
        }

        if ($this->option('view')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('Also generate a read-only view (Infolist) page?', false);
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

    protected function resolveModelClass(): ?string
    {
        $input = $this->option('model') ?: $this->argument('name');
        $class = str_contains($input, '\\') ? $input : 'App\\Models\\'.Str::studly($input);

        if (! class_exists($class)) {
            $this->components->error("Model {$class} doesn't exist. Create it first: `php artisan make:model ".class_basename($class).' -m`.');

            return null;
        }

        if (! is_subclass_of($class, Model::class)) {
            $this->components->error("{$class} exists but isn't an Eloquent model.");

            return null;
        }

        return $class;
    }

    protected function writeResource(Panel $panel, string $path, string $resourceClass, string $modelClass, bool $hasView): void
    {
        $stub = strtr($this->stub('resource'), [
            '{{ resourceNamespace }}' => $panel->getResourcesNamespace(),
            '{{ modelFqcn }}' => $modelClass,
            '{{ modelClass }}' => class_basename($modelClass),
            '{{ resourceClass }}' => $resourceClass,
            '{{ hasViewProperty }}' => $hasView ? "\n    protected static bool \$hasView = true;\n" : '',
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  list<FieldDescriptor>  $fields
     */
    protected function writeRequest(Panel $panel, string $path, string $requestClass, array $fields): void
    {
        $rules = implode("\n", array_map(FieldRenderer::validationRuleLine(...), $fields));

        $stub = strtr($this->stub('request'), [
            '{{ requestsNamespace }}' => $panel->getRequestsNamespace(),
            '{{ requestClass }}' => $requestClass,
            '{{ rules }}' => $rules,
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  array{controllerClass: string, modelClass: string, requestClass: string, requestsNamespace: string, pageBase: string, tableProp: string, modelVariable: string, indexRouteName: string, fields: list<FieldDescriptor>, wantsView: bool}  $data
     */
    protected function writeController(Panel $panel, string $path, array $data): void
    {
        $fields = $data['fields'];
        $modelClass = $data['modelClass'];
        $modelBasename = class_basename($modelClass);
        /** @var Model $modelInstance */
        $modelInstance = new $modelClass;

        $searchable = array_filter($fields, fn ($f) => in_array($f->kind, ['string', 'email', 'text'], true));
        $sortable = array_filter($fields, fn ($f) => ! in_array($f->kind, ['boolean', 'text'], true));

        $defaultSort = Schema::hasColumn($modelInstance->getTable(), 'created_at')
            ? 'created_at'
            : ($fields[0]->name ?? $modelInstance->getKeyName());

        // invue/panels can't composer-depend on invue/notifications (wrong
        // direction), so this only sends a notification when the package
        // is actually installed — same runtime-detection posture as the
        // login page. Filament's own scaffolded resources send a success
        // notification after every create/update/delete by default; this
        // closes that same gap for Invue.
        $modelLabel = Str::headline($modelBasename);
        $hasNotifications = class_exists(NotificationsServiceProvider::class);

        $notificationCall = fn (string $message) => $hasNotifications
            ? "        Notification::make()\n            ->title('{$message}')\n            ->success()\n            ->send();\n"
            : '';

        $showMethod = $data['wantsView']
            ? "\n    public function show({$modelBasename} \${$data['modelVariable']}): Response\n    {\n        return Inertia::render('{$data['pageBase']}/Show', [\n            '{$data['modelVariable']}' => \${$data['modelVariable']},\n        ]);\n    }\n"
            : '';

        $stub = strtr($this->stub('controller'), [
            '{{ controllersNamespace }}' => $panel->getControllersNamespace(),
            '{{ modelUse }}' => "use {$data['modelClass']};",
            '{{ requestUse }}' => "use {$data['requestsNamespace']}\\{$data['requestClass']};",
            '{{ notificationUse }}' => $hasNotifications ? "use Invue\\Notifications\\Notification;\n" : '',
            '{{ controllerClass }}' => $data['controllerClass'],
            '{{ modelClass }}' => $modelBasename,
            '{{ requestClass }}' => $data['requestClass'],
            '{{ pageBase }}' => $data['pageBase'],
            '{{ tableProp }}' => $data['tableProp'],
            '{{ modelVariable }}' => $data['modelVariable'],
            '{{ indexRouteName }}' => $data['indexRouteName'],
            '{{ searchableColumns }}' => $this->quotedList(array_map(fn ($f) => $f->name, $searchable)),
            '{{ sortableColumns }}' => $this->quotedList(array_map(fn ($f) => $f->name, $sortable)),
            '{{ defaultSortColumn }}' => $defaultSort,
            '{{ createdNotification }}' => $notificationCall("{$modelLabel} created"),
            '{{ updatedNotification }}' => $notificationCall("{$modelLabel} updated"),
            '{{ deletedNotification }}' => $notificationCall("{$modelLabel} deleted"),
            '{{ showMethod }}' => $showMethod,
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  array{tableProp: string, navigationLabel: string, modelLabel: string, createUrl: string, editUrlBase: string, primaryKey: string, fields: list<FieldDescriptor>}  $data
     */
    protected function writeIndexPage(string $path, array $data): void
    {
        $fields = $data['fields'];

        $tableImports = array_unique(array_merge(['Table', 'TextColumn', 'ActionsColumn'], array_map(FieldRenderer::tableColumnImport(...), $fields)));
        $tableColumns = implode("\n", array_map(fn ($f) => '            '.FieldRenderer::tableColumn($f), $fields));

        $stub = strtr($this->stub('index.vue'), [
            '%%TABLE_IMPORTS%%' => implode(', ', $tableImports),
            '%%TABLE_PROP%%' => $data['tableProp'],
            '%%NAVIGATION_LABEL%%' => $data['navigationLabel'],
            '%%CREATE_URL%%' => $data['createUrl'],
            '%%EDIT_URL_BASE%%' => $data['editUrlBase'],
            '%%MODEL_LABEL%%' => $data['modelLabel'],
            '%%PRIMARY_KEY%%' => $data['primaryKey'],
            '%%TABLE_COLUMNS%%' => $tableColumns,
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  array{modelLabel: string, navigationLabel: string, indexUrl: string, storeUrl: string, fields: list<FieldDescriptor>}  $data
     */
    protected function writeCreatePage(string $path, array $data): void
    {
        $fields = $data['fields'];

        $stub = strtr($this->stub('create.vue'), [
            '%%FORM_IMPORTS%%' => implode(', ', array_unique(array_map(FieldRenderer::formFieldImport(...), $fields))),
            '%%MODEL_LABEL%%' => $data['modelLabel'],
            '%%NAVIGATION_LABEL%%' => $data['navigationLabel'],
            '%%INDEX_URL%%' => $data['indexUrl'],
            '%%STORE_URL%%' => $data['storeUrl'],
            '%%FORM_INITIAL%%' => implode("\n", array_map(fn ($f) => "    {$f->name}: ".FieldRenderer::defaultValue($f).',', $fields)),
            '%%FORM_FIELD_BINDINGS%%' => $this->fieldBindings($fields),
            '%%FORM_FIELDS%%' => implode("\n", array_map(fn ($f) => '            '.FieldRenderer::formField($f), $fields)),
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  array{modelLabel: string, navigationLabel: string, indexUrl: string, modelVariable: string, primaryKey: string, updateUrlBase: string, fields: list<FieldDescriptor>}  $data
     */
    protected function writeEditPage(string $path, array $data): void
    {
        $fields = $data['fields'];
        $modelVariable = $data['modelVariable'];

        $stub = strtr($this->stub('edit.vue'), [
            '%%FORM_IMPORTS%%' => implode(', ', array_unique(array_map(FieldRenderer::formFieldImport(...), $fields))),
            '%%MODEL_LABEL%%' => $data['modelLabel'],
            '%%NAVIGATION_LABEL%%' => $data['navigationLabel'],
            '%%INDEX_URL%%' => $data['indexUrl'],
            '%%MODEL_VARIABLE%%' => $modelVariable,
            '%%PRIMARY_KEY%%' => $data['primaryKey'],
            '%%UPDATE_URL_BASE%%' => $data['updateUrlBase'],
            '%%FORM_INITIAL_FROM_MODEL%%' => implode("\n", array_map(fn ($f) => "    {$f->name}: props.{$modelVariable}.{$f->name},", $fields)),
            '%%FORM_FIELD_BINDINGS%%' => $this->fieldBindings($fields),
            '%%FORM_FIELDS%%' => implode("\n", array_map(fn ($f) => '            '.FieldRenderer::formField($f), $fields)),
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  array{modelLabel: string, navigationLabel: string, indexUrl: string, editUrlBase: string, primaryKey: string, modelVariable: string, fields: list<FieldDescriptor>}  $data
     */
    protected function writeShowPage(string $path, array $data): void
    {
        $fields = $data['fields'];
        $modelVariable = $data['modelVariable'];

        $tableImports = array_unique(array_map(FieldRenderer::infolistEntryImport(...), $fields));
        $entries = implode("\n", array_map(
            fn (FieldDescriptor $f) => '                '.FieldRenderer::infolistEntry($f, "props.{$modelVariable}"),
            $fields,
        ));

        $stub = strtr($this->stub('show.vue'), [
            '%%TABLE_IMPORTS%%' => implode(', ', $tableImports),
            '%%MODEL_VARIABLE%%' => $modelVariable,
            '%%NAVIGATION_LABEL%%' => $data['navigationLabel'],
            '%%INDEX_URL%%' => $data['indexUrl'],
            '%%MODEL_LABEL%%' => $data['modelLabel'],
            '%%EDIT_URL_BASE%%' => $data['editUrlBase'],
            '%%PRIMARY_KEY%%' => $data['primaryKey'],
            '%%INFOLIST_ENTRIES%%' => $entries,
        ]);

        $this->put($path, $stub);
    }

    /**
     * @param  list<FieldDescriptor>  $fields
     */
    protected function fieldBindings(array $fields): string
    {
        return implode("\n", array_map(
            fn ($f) => "const { modelValue: {$f->camel()}, error: {$f->camel()}Error } = useInvueField(form, '{$f->name}')",
            $fields,
        ));
    }

    /**
     * @param  list<string>  $values
     */
    protected function quotedList(array $values): string
    {
        return implode(', ', array_map(fn (string $v) => "'{$v}'", $values));
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
}
