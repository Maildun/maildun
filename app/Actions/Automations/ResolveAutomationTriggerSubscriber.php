<?php

namespace App\Actions\Automations;

use App\Models\Audience;
use App\Models\Automation;
use App\Models\Subscriber;
use Illuminate\Validation\ValidationException;

class ResolveAutomationTriggerSubscriber
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function handle(Automation $automation, array $payload): Subscriber
    {
        $email = $payload['email'] ?? null;

        if (! is_string($email)) {
            throw ValidationException::withMessages([
                'email' => __('A valid email address is required.'),
            ]);
        }

        $subscriberQuery = Subscriber::query()
            ->where('email', $email)
            ->whereHas('audience', fn ($query) => $query->where('team_id', $automation->team_id));

        $audienceUuid = $payload['audience'] ?? null;

        if (is_string($audienceUuid) && $audienceUuid !== '') {
            $audience = Audience::query()
                ->where('team_id', $automation->team_id)
                ->where('uuid', $audienceUuid)
                ->first();

            if ($audience === null) {
                throw ValidationException::withMessages([
                    'audience' => __('The selected audience is invalid.'),
                ]);
            }

            $subscriberQuery->where('audience_id', $audience->id);
        }

        $subscribers = $subscriberQuery->limit(2)->get();

        if ($subscribers->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => __('No matching subscriber was found.'),
            ]);
        }

        if ($subscribers->count() > 1) {
            throw ValidationException::withMessages([
                'audience' => __('An audience is required when this email belongs to more than one audience.'),
            ]);
        }

        return $subscribers->firstOrFail();
    }
}
