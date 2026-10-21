<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationStatus;
use App\Enums\EmailStatus;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteAudience
{
    /**
     * Delete the audience unless a scheduled or sending campaign still targets it.
     *
     * A campaign loads its recipients from the audience while it sends, and
     * emails.audience_id is nulled on delete, so removing it mid-send crashes the
     * send. Active automations triggered by this audience can never run again,
     * so they are paused rather than left looking active.
     *
     * @return list<string> Names of the automations that were paused.
     *
     * @throws ValidationException
     */
    public function handle(Audience $audience, string $errorKey = 'name'): array
    {
        $campaigns = Email::query()
            ->where('audience_id', $audience->id)
            ->where(fn ($query) => $query
                ->whereIn('status', EmailStatus::active())
                ->orWhere(fn ($scheduled) => $scheduled->where('status', EmailStatus::Draft)->whereNotNull('scheduled_at')))
            ->orderBy('name')
            ->pluck('name');

        if ($campaigns->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => __('This audience is used by scheduled or sending campaigns: :campaigns.', [
                    'campaigns' => $campaigns->join(', '),
                ]),
            ]);
        }

        $automations = DB::transaction(function () use ($audience) {
            $automations = Automation::query()
                ->where('team_id', $audience->team_id)
                ->where('status', AutomationStatus::Active)
                ->where('trigger_config->audience_uuid', $audience->uuid)
                ->orderBy('name')
                ->get();

            Automation::query()
                ->whereKey($automations->modelKeys())
                ->update(['status' => AutomationStatus::Paused]);

            $audience->delete();

            return $automations;
        });

        return array_values($automations->map(fn (Automation $automation): string => $automation->name)->all());
    }
}
