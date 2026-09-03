<?php

namespace App\Http\Middleware;

use App\Actions\Teams\BuildOnboardingChecklist;
use App\Models\Email;
use App\Services\AppUpdateChecker;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private AppUpdateChecker $updates) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'attribution' => [
                'sourceUrl' => config('attribution.source_url'),
            ],
            'auth' => [
                'user' => $user,
            ],
            'registrationOpen' => (bool) config('fortify.registration_open'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'appUpdate' => fn () => $user?->currentTeam && $user->ownsTeam($user->currentTeam)
                ? $this->updates->status()
                : null,
            'recentCampaigns' => fn () => $user?->currentTeam
                ? $user->currentTeam->emails()
                    ->select(['uuid', 'name', 'status', 'updated_at'])
                    ->orderByDesc('updated_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (Email $email): array => [
                        'uuid' => $email->uuid,
                        'name' => $email->name,
                        'status' => $email->status->value,
                        'updated_at' => $email->updated_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
                : [],
            'onboarding' => fn () => $user?->currentTeam
                ? app(BuildOnboardingChecklist::class)->handle($user->currentTeam)
                : null,
        ];
    }
}
