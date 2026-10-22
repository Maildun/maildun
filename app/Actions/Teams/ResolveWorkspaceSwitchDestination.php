<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveWorkspaceSwitchDestination
{
    /**
     * The team ability each permission-gated workspace settings page authorizes
     * before it renders, keyed by route name. Keep in step with the controllers.
     *
     * @var array<string, string>
     */
    protected const GATED_PAGE_ABILITIES = [
        'teams.edit' => 'update',
        'teams.roles.index' => 'manageRoles',
        'teams.email.edit' => 'update',
        'teams.sender.edit' => 'update',
        'teams.email-provider.edit' => 'update',
        'teams.email-provider.create' => 'update',
        'teams.email-provider.show' => 'update',
        'teams.theme.edit' => 'update',
        'teams.api.index' => 'update',
    ];

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

        return $this->urlIsAvailable($candidate, $request) ? $candidate : $dashboard;
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
     * Determine whether the rewritten URL resolves in the destination workspace
     * and the user may open it.
     */
    protected function urlIsAvailable(string $url, Request $originalRequest): bool
    {
        try {
            $request = Request::create($url, 'GET');
            $route = Route::getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);

            app(SubstituteBindings::class)->handle($request, fn () => null);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException|ModelNotFoundException) {
            return false;
        }

        $ability = self::GATED_PAGE_ABILITIES[$route->getName()] ?? null;
        $team = $route->parameter('team');

        if ($ability === null || ! $team instanceof Team) {
            return true;
        }

        return Gate::forUser($originalRequest->user())->allows($ability, $team);
    }
}
