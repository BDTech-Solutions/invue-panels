<?php

namespace Invue\Panels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Invue\Notifications\NotificationsServiceProvider;
use Invue\Panels\Console\Support\ColumnInference;
use Invue\Panels\Console\Support\FieldDescriptor;
use Invue\Panels\Console\Support\FieldRenderer;
use Invue\Panels\Panel;
use Invue\Panels\PanelManager;
use RuntimeException;

/**
 * Automates what was, until now, entirely hand-wired: a `hasMany` relation
 * managed inline on the parent's Edit page (see the Panels doc page's
 * "Relation managers" section). Generates a real Controller + FormRequest
 * for the related model and appends the nested routes — all new, additive
 * files — then PATCHES the parent's already-generated Controller (adds the
 * TableQuery prop to edit()) and Edit.vue (adds the <RelationManager>
 * block). The patches are best-effort, same posture as invue:install: only
 * applied when the target file still matches the exact shape
 * make:invue-resource generates, otherwise the exact snippet is printed to
 * paste by hand instead of guessing against a file that's since been
 * hand-edited.
 */
class MakeRelationManagerCommand extends Command
{
    protected $signature = 'make:invue-relation-manager
        {parent : The parent model name, e.g. Post — must already have a make:invue-resource-generated Edit page}
        {relation : The hasMany relation method name on the parent model, e.g. comments}
        {--panel= : The panel id the parent resource lives in (defaults to the only registered panel)}
        {--title= : RelationManager card title (defaults to a headline of the relation name)}
        {--force : Overwrite the related Controller/Request if they already exist}';

    protected $description = "Scaffold a relation manager (Controller, FormRequest, nested routes) for a hasMany relation, and wire it into the parent's already-generated Edit page";

    protected Filesystem $files;

    public function handle(Filesystem $files, PanelManager $manager): int
    {
        $this->files = $files;

        $panel = $this->resolvePanel($manager);

        if ($panel === null) {
            return self::FAILURE;
        }

        $parentModelClass = $this->resolveModelClass($this->argument('parent'));

        if ($parentModelClass === null) {
            return self::FAILURE;
        }

        $relationName = $this->argument('relation');
        /** @var Model $parentModel */
        $parentModel = new $parentModelClass;

        if (! method_exists($parentModel, $relationName)) {
            $this->components->error("{$parentModelClass} has no {$relationName}() method.");

            return self::FAILURE;
        }

        $relationInstance = $parentModel->{$relationName}();

        if (! $relationInstance instanceof HasMany) {
            $this->components->error(
                "{$parentModelClass}::{$relationName}() is a ".class_basename($relationInstance).
                ', not a hasMany — only hasMany relations are supported right now.'
            );

            return self::FAILURE;
        }

        $relatedModel = $relationInstance->getRelated();
        $relatedModelClass = $relatedModel::class;
        $relatedTable = $relatedModel->getTable();

        if (! Schema::hasTable($relatedTable)) {
            $this->components->error("Table [{$relatedTable}] for {$relatedModelClass} doesn't exist. Migrate it first, then re-run this command.");

            return self::FAILURE;
        }

        $parentBasename = class_basename($parentModelClass);
        $relatedBasename = class_basename($relatedModelClass);
        $parentVariable = Str::camel(Str::singular($parentBasename));
        $relatedVariable = Str::camel(Str::singular($relatedBasename));
        $parentSlug = Str::kebab(Str::pluralStudly($parentBasename));
        $relationSlug = Str::kebab($relationName);
        $parentPageBase = $panel->getPagesNamespace().'/'.Str::pluralStudly($parentBasename);
        // {Parent}{Related}Controller/Request, not just {Related}Controller —
        // avoids colliding with a real standalone Resource for the same
        // related model (e.g. a Comment relation manager on both Post and
        // Video), and with a real Resource controller for the related model
        // itself.
        $controllerClass = "{$parentBasename}{$relatedBasename}Controller";
        $requestClass = "{$parentBasename}{$relatedBasename}Request";

        $fields = ColumnInference::forTable($relatedTable, $relatedModel->getKeyName());

        $targets = [
            'controller' => $panel->getControllersDirectory()."/{$controllerClass}.php",
            'request' => $panel->getRequestsDirectory()."/{$requestClass}.php",
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

        $title = $this->option('title') ?? Str::headline($relationName);

        $this->writeController($panel, $targets['controller'], [
            'controllerClass' => $controllerClass,
            'parentModelClass' => $parentModelClass,
            'relatedModelClass' => $relatedModelClass,
            'requestClass' => $requestClass,
            'requestsNamespace' => $panel->getRequestsNamespace(),
            'parentVariable' => $parentVariable,
            'relatedVariable' => $relatedVariable,
            'relation' => $relationName,
            'foreignKey' => $relationInstance->getForeignKeyName(),
            'parentLocalKey' => $relationInstance->getLocalKeyName(),
            'fields' => $fields,
        ]);
        $this->writeRequest($panel, $targets['request'], $requestClass, $fields);

        $routesWired = $this->registerRoutes($panel, [
            'parentSlug' => $parentSlug,
            'parentVariable' => $parentVariable,
            'relatedVariable' => $relatedVariable,
            'relationSlug' => $relationSlug,
            'routeNamePrefix' => $panel->getRouteNamePrefix()."{$parentSlug}.{$relationSlug}.",
            'controllerClass' => $controllerClass,
            'controllersNamespace' => $panel->getControllersNamespace(),
        ]);

        $controllerPatched = $this->patchParentController([
            'path' => $panel->getControllersDirectory()."/{$parentBasename}Controller.php",
            'parentBasename' => $parentBasename,
            'parentVariable' => $parentVariable,
            'parentPageBase' => $parentPageBase,
            'relation' => $relationName,
            'relationSlug' => $relationSlug,
            'fields' => $fields,
            'relatedModel' => $relatedModel,
        ]);

        $vuePatched = $this->patchParentEditPage([
            'path' => $panel->getPagesDirectory().'/'.Str::pluralStudly($parentBasename).'/Edit.vue',
            'parentVariable' => $parentVariable,
            'relation' => $relationName,
            'relationSlug' => $relationSlug,
            'title' => $title,
            'panelPath' => $panel->getPath(),
            'parentSlug' => $parentSlug,
            'parentLocalKey' => $relationInstance->getLocalKeyName(),
            'relatedPrimaryKey' => $relatedModel->getKeyName(),
            'relatedLabel' => Str::headline($relatedBasename),
            'relatedLabelPlural' => Str::plural(Str::headline($relatedBasename)),
            'fields' => $fields,
        ]);

        $this->components->info("Relation manager [{$relationName}] scaffolded for {$parentBasename}:");
        foreach ($targets as $path) {
            $this->line('  '.$this->relative($path));
        }
        $this->line('  routes/web.php'.($routesWired ? '' : ' (already wired)'));
        $this->line('  '.$this->relative($panel->getControllersDirectory()."/{$parentBasename}Controller.php").($controllerPatched ? ' (patched)' : ' (see snippet above — could not confidently patch)'));
        $this->line('  '.$this->relative($panel->getPagesDirectory().'/'.Str::pluralStudly($parentBasename).'/Edit.vue').($vuePatched ? ' (patched)' : ' (see snippet above — could not confidently patch)'));

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

    protected function resolveModelClass(string $input): ?string
    {
        $class = str_contains($input, '\\') ? $input : 'App\\Models\\'.Str::studly($input);

        if (! class_exists($class)) {
            $this->components->error("Model {$class} doesn't exist.");

            return null;
        }

        if (! is_subclass_of($class, Model::class)) {
            $this->components->error("{$class} exists but isn't an Eloquent model.");

            return null;
        }

        return $class;
    }

    /**
     * @param  array{controllerClass: string, parentModelClass: string, relatedModelClass: string, requestClass: string, requestsNamespace: string, parentVariable: string, relatedVariable: string, relation: string, foreignKey: string, parentLocalKey: string, fields: list<FieldDescriptor>}  $data
     */
    protected function writeController(Panel $panel, string $path, array $data): void
    {
        $hasNotifications = class_exists(NotificationsServiceProvider::class);
        $relatedLabel = Str::headline(class_basename($data['relatedModelClass']));

        $notificationCall = fn (string $message) => $hasNotifications
            ? "        Notification::make()\n            ->title('{$message}')\n            ->success()\n            ->send();\n"
            : '';

        $stub = strtr($this->stub('relation-controller'), [
            '{{ controllersNamespace }}' => $panel->getControllersNamespace(),
            '{{ parentModelUse }}' => "use {$data['parentModelClass']};",
            '{{ relatedModelUse }}' => "use {$data['relatedModelClass']};",
            '{{ requestUse }}' => "use {$data['requestsNamespace']}\\{$data['requestClass']};",
            '{{ notificationUse }}' => $hasNotifications ? "use Invue\\Notifications\\Notification;\n" : '',
            '{{ controllerClass }}' => $data['controllerClass'],
            '{{ parentModelClass }}' => class_basename($data['parentModelClass']),
            '{{ relatedModelClass }}' => class_basename($data['relatedModelClass']),
            '{{ requestClass }}' => $data['requestClass'],
            '{{ parentVariable }}' => $data['parentVariable'],
            '{{ relatedVariable }}' => $data['relatedVariable'],
            '{{ relation }}' => $data['relation'],
            '{{ foreignKey }}' => $data['foreignKey'],
            '{{ parentLocalKey }}' => $data['parentLocalKey'],
            '{{ createdNotification }}' => $notificationCall("{$relatedLabel} added"),
            '{{ deletedNotification }}' => $notificationCall("{$relatedLabel} removed"),
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
     * @param  array{parentSlug: string, parentVariable: string, relatedVariable: string, relationSlug: string, routeNamePrefix: string, controllerClass: string, controllersNamespace: string}  $data
     * @return bool whether it actually appended anything (false = already wired)
     */
    protected function registerRoutes(Panel $panel, array $data): bool
    {
        $path = base_path('routes/web.php');

        if (! $this->files->exists($path)) {
            $this->components->warn('routes/web.php not found — wire the nested routes yourself.');

            return false;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, "'{$data['routeNamePrefix']}'")) {
            return false;
        }

        $middleware = implode(', ', array_map(fn (string $m) => "'{$m}'", $panel->getMiddleware()));
        $prefix = trim($panel->getPath(), '/')."/{$data['parentSlug']}/{{$data['parentVariable']}}";
        $fqcn = "\\{$data['controllersNamespace']}\\{$data['controllerClass']}";

        $block = <<<PHP

Route::middleware([{$middleware}])->prefix('{$prefix}')->name('{$data['routeNamePrefix']}')->group(function () {
    Route::post('/{$data['relationSlug']}', [{$fqcn}::class, 'store'])->name('store');
    Route::delete('/{$data['relationSlug']}/{{$data['relatedVariable']}}', [{$fqcn}::class, 'destroy'])->name('destroy');
});

PHP;

        $this->files->append($path, $block);

        return true;
    }

    /**
     * @param  array{path: string, parentBasename: string, parentVariable: string, parentPageBase: string, relation: string, relationSlug: string, fields: list<FieldDescriptor>, relatedModel: Model}  $data
     */
    protected function patchParentController(array $data): bool
    {
        $path = $data['path'];

        if (! $this->files->exists($path)) {
            $this->components->warn("Parent controller not found at {$this->relative($path)} — run make:invue-resource {$data['parentBasename']} first.");

            return false;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, "'{$data['relationSlug']}' => TableQuery::for(")) {
            return true;
        }

        $fields = $data['fields'];
        $sortable = array_filter($fields, fn (FieldDescriptor $f) => ! in_array($f->kind, ['boolean', 'text'], true));
        $sortableColumns = implode(', ', array_map(fn (FieldDescriptor $f) => "'{$f->name}'", $sortable));
        $defaultSort = Schema::hasColumn($data['relatedModel']->getTable(), 'created_at')
            ? 'created_at'
            : ($fields[0]->name ?? $data['relatedModel']->getKeyName());

        $search = <<<PHP
    public function edit({$data['parentBasename']} \${$data['parentVariable']}): Response
    {
        return Inertia::render('{$data['parentPageBase']}/Edit', [
            '{$data['parentVariable']}' => \${$data['parentVariable']},
        ]);
    }
PHP;

        $replace = <<<PHP
    public function edit(Request \$request, {$data['parentBasename']} \${$data['parentVariable']}): Response
    {
        return Inertia::render('{$data['parentPageBase']}/Edit', [
            '{$data['parentVariable']}' => \${$data['parentVariable']},
            '{$data['relationSlug']}' => TableQuery::for(\${$data['parentVariable']}->{$data['relation']}())
                ->sortable([{$sortableColumns}])
                ->defaultSort('{$defaultSort}', 'desc')
                ->defaultPerPage(5)
                ->paginate(\$request),
        ]);
    }
PHP;

        if (! str_contains($contents, $search)) {
            $this->components->warn("Couldn't confidently patch {$this->relative($path)} — its edit() method doesn't match the shape make:invue-resource generates (already hand-edited?). Add this yourself:");
            $this->line('');
            $this->line($replace);
            $this->line('');

            return false;
        }

        $this->files->put($path, str_replace($search, $replace, $contents));

        return true;
    }

    /**
     * @param  array{path: string, parentVariable: string, relation: string, relationSlug: string, title: string, panelPath: string, parentSlug: string, parentLocalKey: string, relatedPrimaryKey: string, relatedLabel: string, fields: list<FieldDescriptor>}  $data
     */
    protected function patchParentEditPage(array $data): bool
    {
        $path = $data['path'];

        if (! $this->files->exists($path)) {
            $this->components->warn("Parent Edit page not found at {$this->relative($path)} — run make:invue-resource first.");

            return false;
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, 'RelationManager')) {
            return true;
        }

        $fields = $data['fields'];
        $relation = $data['relation'];

        $tableImports = array_unique(array_merge(['Table', 'ActionsColumn'], array_map(FieldRenderer::tableColumnImport(...), $fields)));
        $tableColumns = implode("\n", array_map(fn (FieldDescriptor $f) => '                '.FieldRenderer::tableColumn($f), $fields));
        $formInitial = implode("\n", array_map(fn (FieldDescriptor $f) => "    {$f->name}: ".FieldRenderer::defaultValue($f).',', $fields));
        // Plain <input>/<Checkbox> bound straight to `{relation}Form.{field}`,
        // not useInvueField — this is a compact inline "add" row (see
        // RelationManager.vue's own doc comment), not a full page form, and
        // the parent's own fields already occupy useInvueField's usual
        // destructured-ref names in this same <script setup> scope (e.g. a
        // Post `body` field and a Comment `body` field would collide).
        $formFields = implode("\n", array_map(
            fn (FieldDescriptor $f) => '                    '.$this->directFormField($f, "{$relation}Form"),
            $fields,
        ));

        $relationStudly = Str::studly($relation);
        $nestedBaseUrl = "/{$data['panelPath']}/{$data['parentSlug']}/\${props.{$data['parentVariable']}.{$data['parentLocalKey']}}";

        $panelsImportSearch = "import { PanelLayout } from 'invue/panels'";
        $panelsImportReplace = "import { PanelLayout, RelationManager } from 'invue/panels'";
        $scriptCloseSearch = '</script>';
        $endSearch = "    </PanelLayout>\n</template>";

        if (! str_contains($contents, $panelsImportSearch) || ! str_contains($contents, $endSearch) || substr_count($contents, $scriptCloseSearch) !== 1) {
            $snippet = $this->relationManagerSnippet($data, $tableImports, $tableColumns, $formInitial, $formFields, $relationStudly, $nestedBaseUrl);
            $this->components->warn("Couldn't confidently patch {$this->relative($path)} — it doesn't match the exact shape make:invue-resource generates (already hand-edited?). Add this yourself:");
            $this->line('');
            $this->line($snippet);
            $this->line('');

            return false;
        }

        $scriptAdditions = <<<JS

const {$relation} = useInvueTable('{$relation}')

const {$relation}Form = useForm({
{$formInitial}
})

function submit{$relationStudly}() {
    {$relation}Form.post(`{$nestedBaseUrl}/{$data['relationSlug']}`, {
        preserveScroll: true,
        onSuccess: () => {$relation}Form.reset(),
    })
}

function {$relation}Actions(row) {
    return [
        {
            label: 'Delete',
            icon: 'trash',
            color: 'red',
            url: `{$nestedBaseUrl}/{$data['relationSlug']}/\${row.{$data['relatedPrimaryKey']}}`,
            method: 'delete',
            requiresConfirmation: true,
            confirmationTitle: 'Delete this {$data['relatedLabel']}?',
        },
    ]
}

JS;

        $markup = <<<VUE

        <RelationManager title="{$data['title']}" :count="{$relation}.meta.value?.total ?? null" class="mt-8 max-w-3xl">
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="submit{$relationStudly}">
{$formFields}
                    <button
                        type="submit"
                        class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="{$relation}Form.processing"
                    >
                        Add
                    </button>
                </form>
            </template>

            <Table :table="{$relation}" empty-message="No {$data['relatedLabelPlural']} yet.">
{$tableColumns}
                <ActionsColumn label="Actions" align="end" :actions="{$relation}Actions" />
            </Table>
        </RelationManager>
{$endSearch}
VUE;

        $updated = str_replace($panelsImportSearch, $panelsImportReplace, $contents);
        $updated = str_replace(
            $panelsImportReplace,
            $panelsImportReplace."\nimport { ".implode(', ', $tableImports).", useInvueTable } from 'invue/tables'",
            $updated,
        );
        $updated = str_replace($scriptCloseSearch, $scriptAdditions.$scriptCloseSearch, $updated);
        $updated = str_replace($endSearch, $markup, $updated);

        $this->files->put($path, $updated);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $tableImports
     */
    protected function relationManagerSnippet(
        array $data,
        array $tableImports,
        string $tableColumns,
        string $formInitial,
        string $formFields,
        string $relationStudly,
        string $nestedBaseUrl,
    ): string {
        $relation = $data['relation'];

        return <<<VUE
        // Add to your existing imports:
        // import { PanelLayout, RelationManager } from 'invue/panels'
        // import { {$this->joinImports($tableImports)}, useInvueTable } from 'invue/tables'

        const {$relation} = useInvueTable('{$relation}')
        const {$relation}Form = useForm({
        {$formInitial}
        })

        function submit{$relationStudly}() {
            {$relation}Form.post(`{$nestedBaseUrl}/{$data['relationSlug']}`, { preserveScroll: true, onSuccess: () => {$relation}Form.reset() })
        }

        function {$relation}Actions(row) {
            return [{ label: 'Delete', icon: 'trash', color: 'red', url: `{$nestedBaseUrl}/{$data['relationSlug']}/\${row.{$data['relatedPrimaryKey']}}`, method: 'delete', requiresConfirmation: true, confirmationTitle: 'Delete this {$data['relatedLabel']}?' }]
        }

        <!-- Add just before </PanelLayout>: -->
        <RelationManager title="{$data['title']}" :count="{$relation}.meta.value?.total ?? null" class="mt-8 max-w-3xl">
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="submit{$relationStudly}">
        {$formFields}
                    <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700" :disabled="{$relation}Form.processing">Add</button>
                </form>
            </template>
            <Table :table="{$relation}" empty-message="No {$data['relatedLabelPlural']} yet.">
        {$tableColumns}
                <ActionsColumn label="Actions" align="end" :actions="{$relation}Actions" />
            </Table>
        </RelationManager>
        VUE;
    }

    /**
     * @param  list<string>  $imports
     */
    protected function joinImports(array $imports): string
    {
        return implode(', ', $imports);
    }

    /**
     * A plain HTML field bound straight to `{formVariable}.{field}` — not a
     * real invue/forms component, deliberately: this is a compact inline
     * "add" row inside RelationManager's #actions slot, not a full page
     * form, so there's no per-field error display to wire and no import to
     * add on top of what the parent Edit page already needs.
     */
    protected function directFormField(FieldDescriptor $field, string $formVariable): string
    {
        $binding = "{$formVariable}.{$field->name}";

        if ($field->kind === 'boolean') {
            return sprintf(
                '<label class="flex items-center gap-1.5 text-sm text-gray-600"><input v-model="%s" type="checkbox" class="rounded border-gray-300" /> %s</label>',
                $binding,
                $field->label,
            );
        }

        $type = match ($field->kind) {
            'number' => 'number',
            'email' => 'email',
            'date' => 'date',
            'datetime' => 'datetime-local',
            default => 'text',
        };

        return sprintf(
            '<input v-model="%s" type="%s" placeholder="%s" class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500" />',
            $binding,
            $type,
            $field->label,
        );
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
