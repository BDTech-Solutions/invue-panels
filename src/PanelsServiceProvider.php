<?php

namespace Invue\Panels;

use Illuminate\Support\ServiceProvider;
use Invue\Panels\Console\Commands\MakePanelCommand;
use Invue\Panels\Console\Commands\MakeResourceCommand;

class PanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PanelManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePanelCommand::class,
                MakeResourceCommand::class,
            ]);
        }
    }
}
