<?php

namespace Invue\Panels;

use Illuminate\Support\Str;

/**
 * Base class for generated custom pages — a route plus a real, blank Vue
 * page with no model/CRUD behind it, discovered by directory convention
 * alongside Resource (see PanelManager::discoverPages()). Metadata only,
 * same reasoning as Resource: no PHP UI builder, the .vue file is what you
 * edit.
 */
abstract class Page
{
    protected static string $slug;

    protected static ?string $navigationLabel = null;

    // Every generated page needs *some* icon by default, unlike Resource —
    // Sidebar.vue's `<Icon v-if="item.icon">` renders nothing at all for a
    // falsy icon, and a page has no model to derive a sensible one from the
    // way a Resource could. 'file' is a real, always-resolvable Lucide name
    // via Icon.vue's own lazy fallback (see the common-sense skill), not
    // tied to whatever invue:install happens to register explicitly.
    protected static ?string $navigationIcon = 'file';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationBadgeColor = 'gray';

    public static function getSlug(): string
    {
        return static::$slug;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? Str::headline(static::$slug);
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationBadge(): int|string|null
    {
        return null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return static::$navigationBadgeColor ?? 'gray';
    }

    /**
     * Convention-derived Inertia page component name (relative to
     * resources/js/Pages) — StudlyCase of the slug, matching how
     * make:invue-page names the generated .vue file. Override this in a
     * generated page if the .vue file was moved/renamed by hand.
     */
    public static function getPageComponent(Panel $panel): string
    {
        return $panel->getPagesNamespace().'/'.Str::studly(static::$slug);
    }
}
