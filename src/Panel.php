<?php

namespace Invue\Panels;

use Illuminate\Support\Str;

/**
 * Plain fluent config for one admin area. Deliberately carries no
 * sidebar/topbar configuration — swapping panel UI is a 100% client-side
 * `invue.registry.register('panels.Sidebar', ...)` concern (see the
 * panels/js Base+wrapper components), the same boundary forms/tables
 * already keep between their PHP and Vue sides.
 */
class Panel
{
    protected string $path;

    protected array $middleware = ['web', 'auth'];

    protected ?string $brandName = null;

    protected ?string $brandLogoUrl = null;

    protected ?string $resourcesDirectory = null;

    protected ?string $resourcesNamespace = null;

    protected ?string $controllersNamespace = null;

    protected ?string $controllersDirectory = null;

    protected ?string $requestsNamespace = null;

    protected ?string $requestsDirectory = null;

    protected ?string $pagesDirectory = null;

    protected ?string $pagesNamespace = null;

    protected ?string $pageClassesDirectory = null;

    protected ?string $pageClassesNamespace = null;

    private function __construct(protected readonly string $id)
    {
        $this->path = Str::kebab($id);
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    protected function studlyId(): string
    {
        return Str::studly($this->id);
    }

    public function path(string $path): static
    {
        $this->path = trim($path, '/');

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function brandName(string $name): static
    {
        $this->brandName = $name;

        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName ?? config('app.name') ?? Str::headline($this->id);
    }

    public function brandLogoUrl(string $url): static
    {
        $this->brandLogoUrl = $url;

        return $this;
    }

    public function getBrandLogoUrl(): ?string
    {
        return $this->brandLogoUrl;
    }

    public function resourcesDirectory(string $path): static
    {
        $this->resourcesDirectory = $path;

        return $this;
    }

    public function getResourcesDirectory(): string
    {
        return $this->resourcesDirectory ?? app_path('Invue/'.$this->studlyId().'/Resources');
    }

    public function resourcesNamespace(string $namespace): static
    {
        $this->resourcesNamespace = $namespace;

        return $this;
    }

    public function getResourcesNamespace(): string
    {
        return $this->resourcesNamespace ?? 'App\\Invue\\'.$this->studlyId().'\\Resources';
    }

    public function controllersNamespace(string $namespace): static
    {
        $this->controllersNamespace = $namespace;

        return $this;
    }

    public function getControllersNamespace(): string
    {
        return $this->controllersNamespace ?? 'App\\Http\\Controllers\\Invue\\'.$this->studlyId();
    }

    public function controllersDirectory(string $path): static
    {
        $this->controllersDirectory = $path;

        return $this;
    }

    public function getControllersDirectory(): string
    {
        return $this->controllersDirectory ?? app_path('Http/Controllers/Invue/'.$this->studlyId());
    }

    public function requestsNamespace(string $namespace): static
    {
        $this->requestsNamespace = $namespace;

        return $this;
    }

    public function getRequestsNamespace(): string
    {
        return $this->requestsNamespace ?? 'App\\Http\\Requests\\Invue\\'.$this->studlyId();
    }

    public function requestsDirectory(string $path): static
    {
        $this->requestsDirectory = $path;

        return $this;
    }

    public function getRequestsDirectory(): string
    {
        return $this->requestsDirectory ?? app_path('Http/Requests/Invue/'.$this->studlyId());
    }

    public function pagesDirectory(string $path): static
    {
        $this->pagesDirectory = $path;

        return $this;
    }

    public function getPagesDirectory(): string
    {
        return $this->pagesDirectory ?? resource_path('js/Pages/Invue/'.$this->studlyId());
    }

    /**
     * The Inertia page-name prefix (relative to resources/js/Pages) matching
     * getPagesDirectory() under Laravel/Inertia's default convention.
     */
    public function getPagesNamespace(): string
    {
        return $this->pagesNamespace ?? 'Invue/'.$this->studlyId();
    }

    public function pagesNamespace(string $namespace): static
    {
        $this->pagesNamespace = $namespace;

        return $this;
    }

    public function pageClassesDirectory(string $path): static
    {
        $this->pageClassesDirectory = $path;

        return $this;
    }

    /**
     * Where make:invue-page's generated {Name}Page.php classes live —
     * distinct from getPagesDirectory(), which is the .vue side of the same
     * feature. Mirrors getResourcesDirectory()'s own convention.
     */
    public function getPageClassesDirectory(): string
    {
        return $this->pageClassesDirectory ?? app_path('Invue/'.$this->studlyId().'/Pages');
    }

    public function pageClassesNamespace(string $namespace): static
    {
        $this->pageClassesNamespace = $namespace;

        return $this;
    }

    public function getPageClassesNamespace(): string
    {
        return $this->pageClassesNamespace ?? 'App\\Invue\\'.$this->studlyId().'\\Pages';
    }

    public function getRouteNamePrefix(): string
    {
        return "invue.{$this->id}.";
    }
}
