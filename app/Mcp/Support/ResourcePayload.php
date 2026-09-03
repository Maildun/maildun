<?php

namespace App\Mcp\Support;

use App\Models\Audience;
use App\Models\Automation;
use App\Models\Email;
use App\Models\Team;
use App\Models\TransactionalEmail;

class ResourcePayload
{
    /**
     * @return array<string, mixed>
     */
    public function workspace(Team $team): array
    {
        return [
            'uuid' => $team->uuid,
            'name' => $team->name,
            'slug' => $team->slug,
            'email_editor' => $team->email_editor->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function audience(Audience $audience): array
    {
        return [
            'uuid' => $audience->uuid,
            'name' => $audience->name,
            'description' => $audience->description,
            'first_name_mode' => $audience->first_name_mode->value,
            'last_name_mode' => $audience->last_name_mode->value,
            'from_name' => $audience->from_name,
            'from_address' => $audience->from_address,
            'reply_to' => $audience->reply_to,
            'notification_email' => $audience->notification_email,
            'subscribed_url' => $audience->subscribed_url,
            'already_subscribed_url' => $audience->already_subscribed_url,
            'unsubscribed_url' => $audience->unsubscribed_url,
            'subscribers_count' => (int) $audience->getAttribute('subscribers_count'),
            'subscribed_count' => (int) $audience->getAttribute('subscribed_count'),
            'segments_count' => (int) $audience->getAttribute('segments_count'),
            'created_at' => $audience->created_at?->toISOString(),
            'updated_at' => $audience->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignSummary(Email $campaign): array
    {
        return [
            'uuid' => $campaign->uuid,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'editor' => $campaign->editor->value,
            'status' => $campaign->status->value,
            'audience' => $campaign->audience === null ? null : [
                'uuid' => $campaign->audience->uuid,
                'name' => $campaign->audience->name,
            ],
            'segment' => $campaign->segment === null ? null : [
                'uuid' => $campaign->segment->uuid,
                'name' => $campaign->segment->name,
            ],
            'recipient_count' => $campaign->recipient_count,
            'updated_at' => $campaign->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaign(Email $campaign): array
    {
        return [
            ...$this->campaignSummary($campaign),
            'preheader' => $campaign->preheader,
            'from_name' => $campaign->from_name,
            'from_address' => $campaign->from_address,
            'reply_to' => $campaign->reply_to,
            'html' => $campaign->html,
            'source' => $campaign->source,
            'plain_text' => $campaign->plain_text,
            'query_string' => $campaign->query_string,
            'track_clicks' => $campaign->track_clicks,
            'track_opens' => $campaign->track_opens,
            'design' => $campaign->design,
            'send_started_at' => $campaign->send_started_at?->toISOString(),
            'sent_at' => $campaign->sent_at?->toISOString(),
            'created_at' => $campaign->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionalEmailSummary(TransactionalEmail $email): array
    {
        return [
            'uuid' => $email->uuid,
            'name' => $email->name,
            'slug' => $email->slug,
            'subject' => $email->subject,
            'editor' => $email->editor->value,
            'status' => $email->status->value,
            'published_at' => $email->published_at?->toISOString(),
            'updated_at' => $email->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionalEmail(TransactionalEmail $email): array
    {
        return [
            ...$this->transactionalEmailSummary($email),
            'description' => $email->description,
            'preheader' => $email->preheader,
            'from_name' => $email->from_name,
            'from_address' => $email->from_address,
            'reply_to' => $email->reply_to,
            'html' => $email->html,
            'source' => $email->source,
            'design' => $email->design,
            'variables' => $email->variables,
            'slug_frozen' => $email->slugIsFrozen(),
            'created_at' => $email->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function automationSummary(Automation $automation): array
    {
        return [
            'uuid' => $automation->uuid,
            'name' => $automation->name,
            'description' => $automation->description,
            'status' => $automation->status->value,
            'trigger' => $automation->trigger->value,
            'trigger_label' => $automation->trigger->label(),
            'enrolled_count' => (int) $automation->getAttribute('enrolled_count'),
            'running_count' => (int) $automation->getAttribute('running_count'),
            'updated_at' => $automation->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function automation(Automation $automation): array
    {
        return [
            ...$this->automationSummary($automation),
            'trigger_config' => $automation->trigger_config,
            'graph' => $automation->graph,
            'created_at' => $automation->created_at?->toISOString(),
        ];
    }
}
