<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppUpdateChecker;
use App\Services\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SystemCheckController extends Controller
{
    private const string STORAGE_PROBE_KEY = 'system-check.storage-probe';

    /**
     * Display the current deployment diagnostics.
     */
    public function show(Request $request, InstallationState $installation, AppUpdateChecker $updates): Response
    {
        $this->authorizeOwner($request);

        // Only the storage round-trip is remembered from the last system
        // test; queue, Horizon, scheduler and send health stay live.
        $storageProbe = $request->session()->get(self::STORAGE_PROBE_KEY);
        $checks = array_map(
            fn (array $check): array => $check['key'] === 'storage-round-trip' && is_array($storageProbe)
                ? $storageProbe
                : $check,
            $installation->systemChecks(),
        );

        return Inertia::render('settings/system-check', [
            'checks' => $checks,
            'appUpdate' => $updates->status(),
        ]);
    }

    /**
     * Run the storage round-trip diagnostic and return to the status page.
     */
    public function test(Request $request, InstallationState $installation): RedirectResponse
    {
        $this->authorizeOwner($request);

        $storageProbe = collect($installation->systemChecks(probeStorage: true))
            ->firstWhere('key', 'storage-round-trip');

        $request->session()->put(self::STORAGE_PROBE_KEY, $storageProbe);

        return to_route('system-check.show');
    }

    /**
     * Fetch the release manifest immediately without installing anything.
     */
    public function refreshUpdate(Request $request, AppUpdateChecker $updates): RedirectResponse
    {
        $this->authorizeOwner($request);

        if ($updates->refresh()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Maildun update status refreshed.')]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Maildun could not check for updates. Please try again later.')]);
        }

        return to_route('system-check.show');
    }

    /**
     * Restrict deployment diagnostics to the active workspace owner.
     */
    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->currentTeam !== null, 403);

        Gate::authorize('viewSystemCheck', $user->currentTeam);
    }
}
