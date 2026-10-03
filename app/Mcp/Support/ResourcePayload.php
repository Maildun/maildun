<?php

namespace App\Mcp\Support;

use App\Models\Audience;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
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
            'language_mode' => $audience->language_mode->value,
            'allowed_languages' => $audience->allowed_languages ?? [],
            'default_language' => $audience->default_language?->value,
            'from_name' => $audience->from_name,
            'from_address' => $audience->from_address,
            'reply_to' => $audience->reply_to,
            'notification_email' => $audience->notification_email,
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
            'scheduled_at' => $campaign->scheduled_at?->toISOString(),
            'schedule_error' => $campaign->schedule_error,
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

    /**
     * @return array<string, mixed>
     */
    public function contactSummary(Contact $contact): array
    {
        return [
            'uuid' => $contact->uuid,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'company' => $contact->company === null ? null : [
                'uuid' => $contact->company->uuid,
                'name' => $contact->company->name,
            ],
            'tags' => $contact->tags->pluck('name')->sortBy(fn (string $name): string => mb_strtolower($name))->values()->all(),
            'audiences_count' => (int) ($contact->getAttribute('subscribers_count') ?? $contact->subscribers()->count()),
            'created_at' => $contact->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contact(Contact $contact): array
    {
        return [
            ...$this->contactSummary($contact),
            'memberships' => $contact->subscribers
                ->map(fn (Subscriber $subscriber): array => [
                    'subscriber_uuid' => $subscriber->uuid,
                    'audience' => [
                        'uuid' => $subscriber->audience->uuid,
                        'name' => $subscriber->audience->name,
                    ],
                    'status' => $subscriber->status->value,
                    'pending_confirmation' => $subscriber->isPendingConfirmation(),
                    'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
                    'unsubscribed_at' => $subscriber->unsubscribed_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'updated_at' => $contact->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriber(Subscriber $subscriber): array
    {
        return [
            'uuid' => $subscriber->uuid,
            'contact_uuid' => $subscriber->contact?->uuid,
            'audience' => [
                'uuid' => $subscriber->audience->uuid,
                'name' => $subscriber->audience->name,
            ],
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'status' => $subscriber->status->value,
            'pending_confirmation' => $subscriber->isPendingConfirmation(),
            'source' => $subscriber->source->value,
            'tags' => $subscriber->tags->pluck('name')->sortBy(fn (string $name): string => mb_strtolower($name))->values()->all(),
            'attribute_values' => $subscriber->attribute_values ?? [],
            'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
            'unsubscribed_at' => $subscriber->unsubscribed_at?->toISOString(),
            'created_at' => $subscriber->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function segment(Segment $segment): array
    {
        return [
            'uuid' => $segment->uuid,
            'audience' => [
                'uuid' => $segment->audience->uuid,
                'name' => $segment->audience->name,
            ],
            'name' => $segment->name,
            'description' => $segment->description,
            'match_type' => $segment->match_type->value,
            'rules' => $segment->rules,
            'subscribers_count' => (int) ($segment->getAttribute('subscribers_count') ?? $segment->subscribers()->count()),
            'rules_synced_at' => $segment->rules_synced_at?->toISOString(),
            'created_at' => $segment->created_at?->toISOString(),
            'updated_at' => $segment->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tag(Tag $tag): array
    {
        return [
            'uuid' => $tag->uuid,
            'name' => $tag->name,
            'color' => $tag->color,
            'contacts_count' => (int) $tag->getAttribute('contacts_count'),
        ];
    }
}
