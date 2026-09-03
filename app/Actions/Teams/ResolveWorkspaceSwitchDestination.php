<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveWorkspaceSwitchDestination
{
    /**
     * Resolve the URL to send the user to after switching workspaces.
     *
     * Keep the current page when the equivalent URL exists in the destination
     * workspace; otherwise send them to that workspace's dashboard.
     */
    public function handle(Request $request, Team $team, ?Team $previousTeam): string
    {
        $dashboard = route('dashboard', ['current_team' => $team]);
        $previousUrl = url()->previous();

        try {
            $uri = Uri::of($previousUrl);
        } catch (\Throwable) {
            return $dashboard;
        }

        if ($uri->host() !== $request->getHost()) {
            return $dashboard;
        }

        $path = '/'.ltrim($uri->path(), '/');

        if ($path === '/') {
            return $dashboard;
        }

        $rewrittenPath = $this->rewriteWorkspacePath($path, $team, $previousTeam);
        $query = parse_url($previousUrl, PHP_URL_QUERY);
        $candidate = $rewrittenPath.(is_string($query) && $query !== '' ? '?'.$query : '');

        return $this->urlIsAvailable($candidate) ? $candidate : $dashboard;
    }

    /**
     * Swap the previous workspace slug for the destination workspace slug.
     */
    protected function rewriteWorkspacePath(string $path, Team $team, ?Team $previousTeam): string
    {
        $previousSlug = $previousTeam?->slug;

        if ($previousSlug === null || $previousSlug === $team->slug) {
            return $path;
        }

        $settingsPrefix = '/settings/workspace/'.$previousSlug;

        if ($path === $settingsPrefix || str_starts_with($path, $settingsPrefix.'/')) {
            return '/settings/workspace/'.$team->slug.substr($path, strlen($settingsPrefix));
        }

        $appPrefix = '/'.$previousSlug;

        if ($path === $appPrefix || str_starts_with($path, $appPrefix.'/')) {
            return '/'.$team->slug.substr($path, strlen($appPrefix));
        }

        return $path;
    }

    /**
     * Determine whether the rewritten URL can be resolved for the destination workspace.
     */
    protected function urlIsAvailable(string $url): bool
    {
        try {
            $request = Request::create($url, 'GET');
            $route = Route::getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);

            app(SubstituteBindings::class)->handle($request, fn () => null);

            return true;
        } catch (NotFoundHttpException|MethodNotAllowedHttpException|ModelNotFoundException) {
            return false;
        }
    }
}
