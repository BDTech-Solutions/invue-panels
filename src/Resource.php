<?php

namespace Invue\Panels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base class for generated `{Model}Resource` classes. Metadata only — no
 * `form()`/`table()` builder methods, deliberately: field/column layout
 * lives in the generated `.vue` pages (real, editable files), matching
 * Invue's "no PHP UI builder" rule already established by invue/forms and
 * invue/tables.
 */
abstract class Resource
{
    /** @var class-string<Model> */
    protected static string $model;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationBadgeColor = 'gray';

    /**
     * Set by a generated Resource when make:invue-resource scaffolded a
     * read-only Show (Infolist) page alongside it — PanelManager uses this
     * to decide whether the `show` resource route is actually reachable
     * for this Resource. False by default: a plain Resource has no
     * Controller::show() to route to.
     */
    protected static bool $hasView = false;

    public static function getModel(): string
    {
        return static::$model;
    }

    public static function getModelLabel(): string
    {
        return Str::headline(class_basename(static::getModel()));
    }

    public static function getPluralModelLabel(): string
    {
        return Str::plural(static::getModelLabel());
    }

    public static function getSlug(): string
    {
        return Str::kebab(Str::pluralStudly(class_basename(static::getModel())));
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    /**
     * Overridden by a generated Resource to show a count/status pill next
     * to its nav item — null (the default) renders no badge at all. A
     * closure/query so it stays lazy: `PanelManager::navigationFor()` only
     * evaluates it while actually building the Sidebar's `navigation`
     * prop, not on every request that touches this class.
     */
    public static function getNavigationBadge(): int|string|null
    {
        return null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return static::$navigationBadgeColor ?? 'gray';
    }

    public static function hasView(): bool
    {
        return static::$hasView;
    }

    /**
     * Convention-derived controller FQCN. Override this in a generated
     * Resource if the controller was moved/renamed by hand.
     */
    public static function getControllerClass(Panel $panel): string
    {
        return $panel->getControllersNamespace().'\\'.class_basename(static::getModel()).'Controller';
    }
}
