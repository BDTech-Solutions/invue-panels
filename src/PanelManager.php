<?php

namespace Invue\Panels;

use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Singleton registry of booted Panels. Resources are discovered by
 * directory convention (every `*Resource.php` under a Panel's configured
 * resources directory) rather than registered one-by-one — that call was
 * made explicitly with the project owner: convenience over an explicit
 * per-resource registration list.
 */
class PanelManager
{
    /** @var array<string, Panel> */
    protected array $panels = [];

    public function register(Panel $panel): Panel
    {
        $this->panels[$panel->getId()] = $panel;

        return $panel;
    }

    public function get(string $id): Panel
    {
        return $this->panels[$id] ?? throw new RuntimeException(
            "No Invue panel registered with id [{$id}]. Registered panels: ".
            (empty($this->panels) ? '(none — run `php artisan make:invue-panel`)' : implode(', ', array_keys($this->panels)))
        );
    }

    /**
     * @return array<string, Panel>
     */
    public function all(): array
    {
        return $this->panels;
    }

    /**
     * The single registered panel — used by make:invue-resource when
     * --panel isn't passed and there's no ambiguity.
     */
    public function sole(): Panel
    {
        if (count($this->panels) === 1) {
            return array_values($this->panels)[0];
        }

        throw new RuntimeException(
            empty($this->panels)
                ? 'No Invue panels are registered. Run `php artisan make:invue-panel` first.'
                : 'Multiple Invue panels are registered ('.implode(', ', array_keys($this->panels)).'); pass --panel explicitly.'
        );
    }

    public function forPath(string $path): ?Panel
    {
        $path = trim($path, '/');

        foreach ($this->panels as $panel) {
            $prefix = trim($panel->getPath(), '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * @return list<string> FQCNs of Resource subclasses — not typed as
     *                      class-string<Resource>: Pint's phpdoc_types fixer
     *                      collides that with PHP's builtin `resource`
     *                      pseudo-type and force-lowercases it every run.
     */
    public function discoverResources(Panel $panel): array
    {
        $directory = $panel->getResourcesDirectory();

        if (! is_dir($directory)) {
            return [];
        }

        $resources = [];

        foreach (glob($directory.'/*Resource.php') ?: [] as $file) {
            $class = $panel->getResourcesNamespace().'\\'.basename($file, '.php');

            if (! class_exists($class)) {
                throw new RuntimeException("Invue panel [{$panel->getId()}] found {$file} but class {$class} doesn't exist — check that its namespace and class name match its path.");
            }

            if (! is_subclass_of($class, Resource::class)) {
                throw new RuntimeException("Invue panel [{$panel->getId()}] found {$class} in its resources directory, but it doesn't extend ".Resource::class.'.');
            }

            $resources[] = $class;
        }

        return $resources;
    }

    /**
     * @return list<array{label: string, icon: ?string, group: ?string, url: string, badge: int|string|null, badgeColor: string}>
     */
    public function navigationFor(Panel $panel): array
    {
        return array_map(
            fn (string $resource) => [
                'label' => $resource::getNavigationLabel(),
                'icon' => $resource::getNavigationIcon(),
                'group' => $resource::getNavigationGroup(),
                'url' => '/'.trim($panel->getPath(), '/').'/'.$resource::getSlug(),
                'badge' => $resource::getNavigationBadge(),
                'badgeColor' => $resource::getNavigationBadgeColor(),
            ],
            $this->discoverResources($panel),
        );
    }

    public function registerRoutes(Panel $panel): void
    {
        $resources = $this->discoverResources($panel);

        if ($resources === []) {
            return;
        }

        Route::middleware([...$panel->getMiddleware(), Http\Middleware\ShareInvuePanelData::class])
            ->prefix($panel->getPath())
            ->name($panel->getRouteNamePrefix())
            ->group(function () use ($resources, $panel): void {
                foreach ($resources as $resource) {
                    Route::resource($resource::getSlug(), $resource::getControllerClass($panel))
                        ->except('show');
                }
            });
    }
}
