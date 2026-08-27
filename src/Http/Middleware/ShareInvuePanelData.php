<?php

namespace Invue\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Invue\Panels\PanelManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the one Inertia prop (`invuePanel`) a Sidebar/Topbar needs to
 * render — this plain array is the entire contract a swappable panel-shell
 * component (built-in or a future third-party "store" package) has to
 * consume, the same decoupling TableQuery's array shape gives useInvueTable.
 */
class ShareInvuePanelData
{
    public function __construct(protected PanelManager $panels) {}

    public function handle(Request $request, Closure $next): Response
    {
        $panel = $this->panels->forPath($request->path());

        if ($panel !== null) {
            Inertia::share('invuePanel', [
                'id' => $panel->getId(),
                'brandName' => $panel->getBrandName(),
                'brandLogoUrl' => $panel->getBrandLogoUrl(),
                'navigation' => $this->panels->navigationFor($panel),
                'current' => '/'.$request->path(),
            ]);
        }

        return $next($request);
    }
}
