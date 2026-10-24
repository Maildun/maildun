<?php

namespace App\Actions\Emails;

use App\Models\Team;
use Illuminate\Support\Facades\RateLimiter;

class ConsumeAssistantRequest
{
    /**
     * Counts one design assistant request against the workspace's allowance
     * for the rolling 24 hours (config `assistant.daily_limit`, 0 for none).
     * Returns why the request is refused, or null when it may go ahead.
     */
    public function handle(Team $team): ?string
    {
        $dailyLimit = (int) config('assistant.daily_limit', 100);
        $quotaKey = "design-assistant:team:{$team->id}";

        if ($dailyLimit <= 0) {
            return null;
        }

        if (RateLimiter::tooManyAttempts($quotaKey, $dailyLimit)) {
            return __('This workspace has used its design assistant requests for today. Try again in :hours hours.', [
                'hours' => max(1, (int) ceil(RateLimiter::availableIn($quotaKey) / 3600)),
            ]);
        }

        RateLimiter::hit($quotaKey, 86400);

        return null;
    }
}
