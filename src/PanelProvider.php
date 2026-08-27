<?php

namespace Invue\Panels;

use Illuminate\Support\ServiceProvider;

/**
 * A consuming app extends this once per admin area, e.g.
 * `app/Providers/AdminPanelProvider.php extends Invue\Panels\PanelProvider`,
 * registered in bootstrap/providers.php (make:invue-panel does this for
 * you). Mirrors Filament\PanelProvider's shape.
 */
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(): Panel;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $manager = $this->app->make(PanelManager::class);
        $panel = $manager->register($this->panel());
        $manager->registerRoutes($panel);
    }
}
