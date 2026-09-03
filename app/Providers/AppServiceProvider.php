<?php

namespace App\Providers;

use App\Contracts\DnsRecordLookup;
use App\Contracts\DnsResolver;
use App\Models\Automation;
use App\Models\EmailDelivery;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Services\SystemDnsRecordLookup;
use App\Services\SystemDnsResolver;
use App\Services\TeamMailer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(DnsRecordLookup::class, SystemDnsRecordLookup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
        $this->configureRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for(TeamMailer::RATE_LIMITER, function (): Limit {
            $perSecond = (int) config('delivery.rate_limit.per_second');

            return $perSecond > 0 ? Limit::perSecond($perSecond) : Limit::none();
        });

        RateLimiter::for('public-subscribe', function (Request $request): array {
            $subscribeForm = $request->route('subscribeForm');
            $formKey = $subscribeForm instanceof SubscribeForm ? $subscribeForm->uuid : (string) $subscribeForm;
            $email = Str::lower($request->string('email')->trim()->toString());

            return [
                Limit::perMinute(10)->by('public-subscribe:ip:'.$formKey.':'.$request->ip()),
                Limit::perHour(3)->by('public-subscribe:email:'.$formKey.':'.$email),
            ];
        });

        // Keyed by the recipient rather than IP: one-click requests from a mail
        // provider all share that provider's addresses, so an IP bucket would
        // throttle unrelated recipients out of opting out. Campaign mail keys on
        // the delivery, automation mail on the subscriber — whichever the route
        // carries. Falling back to a shared bucket would recreate the very
        // problem this avoids, so an unkeyed request gets its own IP bucket.
        RateLimiter::for('public-unsubscribe', function (Request $request): array {
            $target = $request->route('delivery') ?? $request->route('subscriber');

            $key = match (true) {
                $target instanceof EmailDelivery => 'delivery:'.$target->uuid,
                $target instanceof Subscriber => 'subscriber:'.$target->uuid,
                filled($target) => 'route:'.(string) $target,
                default => 'ip:'.$request->ip(),
            };

            return [
                Limit::perMinute(10)->by('public-unsubscribe:'.$key),
            ];
        });

        RateLimiter::for('automation-trigger', function (Request $request): array {
            $automation = $request->route('automation');
            $key = $automation instanceof Automation ? $automation->uuid : (string) $automation;
            $providedToken = $request->header('X-Automation-Token') ?: $request->bearerToken();
            $tokenFingerprint = hash('sha256', is_string($providedToken) ? $providedToken : '');

            return [
                Limit::perMinute(60)->by('automation-trigger:token:'.$key.':'.$tokenFingerprint),
                Limit::perMinute(120)->by('automation-trigger:ip:'.$key.':'.$request->ip()),
            ];
        });

        RateLimiter::for('team-api-auth', function (Request $request): array {
            $providedToken = $request->bearerToken();
            $fingerprint = hash('sha256', is_string($providedToken) ? $providedToken : '');

            return [
                Limit::perMinute(120)->by('team-api-auth:token:'.$fingerprint),
                Limit::perMinute(240)->by('team-api-auth:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('team-api', function (Request $request): Limit {
            $apiKey = $request->attributes->get('teamApiKey');
            $key = $apiKey instanceof TeamApiKey ? $apiKey->uuid : $request->ip();

            return Limit::perMinute(120)->by('team-api:'.$key);
        });

        RateLimiter::for('mcp-oauth', function (Request $request): array {
            return [
                Limit::perMinute(60)->by('mcp-oauth:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('mcp-auth', function (Request $request): array {
            $providedToken = $request->bearerToken();
            $fingerprint = hash('sha256', is_string($providedToken) ? $providedToken : '');

            return [
                Limit::perMinute(120)->by('mcp-auth:token:'.$fingerprint),
                Limit::perMinute(240)->by('mcp-auth:ip:'.$request->ip()),
            ];
        });
    }

    protected function configurePassport(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(now()->addMinutes(max(
            5,
            (int) config('mcp.access_token_expiration_minutes'),
        )));
        Passport::refreshTokensExpireIn(now()->addDays(max(
            1,
            (int) config('mcp.refresh_token_expiration_days'),
        )));
    }
}
