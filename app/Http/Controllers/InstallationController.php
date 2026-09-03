<?php

namespace App\Http\Controllers;

use App\Actions\Install\CompleteBrowserInstallation;
use App\Http\Requests\Install\StoreInstallationRequest;
use App\Services\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InstallationController extends Controller
{
    public function show(Request $request, InstallationState $installation): Response
    {
        $checks = $installation->checks();

        return Inertia::render('auth/install', [
            'checks' => $checks,
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ready']),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'signedQuery' => [
                'expires' => (string) $request->query('expires'),
                'signature' => (string) $request->query('signature'),
            ],
            'systemTestUrl' => $this->signedPath('install.system.show', $request),
        ]);
    }

    public function system(Request $request, InstallationState $installation): Response
    {
        $checks = $request->session()->get(
            'installation.system_checks',
            $installation->systemChecks(),
        );

        return Inertia::render('auth/install-system', [
            'checks' => $checks,
            'installUrl' => $this->signedPath('install.show', $request),
            'signedQuery' => [
                'expires' => (string) $request->query('expires'),
                'signature' => (string) $request->query('signature'),
            ],
        ]);
    }

    public function testSystem(Request $request, InstallationState $installation): RedirectResponse
    {
        $request->session()->flash(
            'installation.system_checks',
            $installation->systemChecks(probeStorage: true),
        );

        return back();
    }

    public function store(
        StoreInstallationRequest $request,
        CompleteBrowserInstallation $completeInstallation,
    ): RedirectResponse {
        $user = $completeInstallation->handle($request->installationData());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard', ['current_team' => $user->currentTeam]);
    }

    private function signedPath(string $route, Request $request): string
    {
        return URL::temporarySignedRoute(
            $route,
            now()->setTimestamp($request->integer('expires')),
            absolute: false,
        );
    }
}
