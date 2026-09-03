<?php

namespace App\Http\Controllers;

use App\Enums\AudienceAttributeType;
use App\Enums\SubscriberStatus;
use App\Enums\TransactionalEmailStatus;
use App\Http\Requests\SaveAudienceRequest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Team;
use App\Models\TeamSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AudienceSettingsController extends Controller
{
    public function general(Request $request, Team $currentTeam, Audience $audience): Response|RedirectResponse
    {
        Gate::authorize('update', $audience);

        if ($redirect = $this->legacyTabRedirect($request, $currentTeam, $audience)) {
            return $redirect;
        }

        $audience->loadCount([
            'subscribers',
            'subscribers as subscribed_count' => fn ($query) => $query->where('status', SubscriberStatus::Subscribed),
            'segments',
            'subscribeForms',
        ]);

        return Inertia::render('audiences/edit', [
            'audience' => $this->audiencePayload($audience),
            'stats' => [
                'subscribers' => $audience->subscribers_count,
                'subscribed' => $audience->subscribed_count,
                'segments' => $audience->segments_count,
                'forms' => $audience->subscribe_forms_count,
                'created_at' => $audience->created_at->toISOString(),
            ],
        ]);
    }

    public function sender(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        $senders = $currentTeam->verifiedSenders()
            ->orderByDesc('id')
            ->get();

        $selectedSenderUuid = null;

        if ($audience->from_name === null && $audience->from_address === null && $audience->reply_to === null) {
            $selectedSenderUuid = SaveAudienceRequest::WORKSPACE_DEFAULT_SENDER;
        } elseif ($audience->from_address !== null) {
            $selectedSenderUuid = $senders
                ->first(fn (TeamSender $sender): bool => strcasecmp($sender->email, trim($audience->from_address)) === 0)
                ?->uuid;
        }

        return Inertia::render('audiences/settings/sender', [
            'audience' => $this->audiencePayload($audience),
            'senders' => $senders
                ->map(fn (TeamSender $sender): array => [
                    'uuid' => $sender->uuid,
                    'name' => $sender->name,
                    'email' => $sender->email,
                    'reply_to' => $sender->reply_to,
                ])
                ->values()
                ->all(),
            'selectedSenderUuid' => $selectedSenderUuid,
            'senderFallbacks' => [
                'from_name' => $currentTeam->email_from_name ?? config('mail.from.name'),
                'from_address' => $currentTeam->email_from_address ?? config('mail.from.address'),
                'reply_to' => $currentTeam->email_reply_to,
            ],
        ]);
    }

    public function notifications(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        return Inertia::render('audiences/settings/notifications', [
            'audience' => $this->audiencePayload($audience),
        ]);
    }

    public function doubleOptIn(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        return Inertia::render('audiences/settings/double-opt-in', [
            'audience' => $this->audiencePayload($audience),
            'transactionalEmails' => $currentTeam->transactionalEmails()
                ->where(function ($query) use ($audience): void {
                    $query
                        ->where('status', TransactionalEmailStatus::Published->value)
                        ->orWhere('id', $audience->double_opt_in_email_id);
                })
                ->orderBy('name')
                ->get(['uuid', 'name', 'subject', 'status'])
                ->map(fn ($email): array => [
                    'uuid' => $email->uuid,
                    'name' => $email->name,
                    'subject' => $email->subject,
                    'published' => $email->status === TransactionalEmailStatus::Published,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function attributes(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        return Inertia::render('audiences/settings/attributes', [
            'audience' => $this->audiencePayload($audience),
            'attributes' => $audience->audienceAttributes()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (AudienceAttribute $attribute) => [
                    'uuid' => $attribute->uuid,
                    'name' => $attribute->name,
                    'key' => $attribute->key,
                    'type' => $attribute->type->value,
                    'required' => $attribute->required,
                ]),
            'attributeTypes' => AudienceAttributeType::options(),
        ]);
    }

    public function landingPages(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        return Inertia::render('audiences/settings/landing-pages', [
            'audience' => $this->audiencePayload($audience),
        ]);
    }

    public function danger(Team $currentTeam, Audience $audience): Response
    {
        Gate::authorize('update', $audience);

        $audience->loadCount(['subscribers', 'segments', 'subscribeForms']);

        return Inertia::render('audiences/settings/danger', [
            'audience' => [
                ...$this->audiencePayload($audience),
                'subscribers_count' => $audience->subscribers_count,
                'segments_count' => $audience->segments_count,
                'forms_count' => $audience->subscribe_forms_count,
            ],
        ]);
    }

    /**
     * @return array{
     *     uuid: string,
     *     name: string,
     *     description: string|null,
     *     first_name_mode: string,
     *     last_name_mode: string,
     *     double_opt_in: bool,
     *     double_opt_in_email_uuid: string|null,
     *     avatar: string,
     *     from_name: string|null,
     *     from_address: string|null,
     *     reply_to: string|null,
     *     notification_email: string|null,
     *     subscribed_url: string|null,
     *     already_subscribed_url: string|null,
     *     unsubscribed_url: string|null
     * }
     */
    private function audiencePayload(Audience $audience): array
    {
        return [
            'uuid' => $audience->uuid,
            'name' => $audience->name,
            'description' => $audience->description,
            'first_name_mode' => $audience->first_name_mode->value,
            'last_name_mode' => $audience->last_name_mode->value,
            'double_opt_in' => $audience->double_opt_in,
            'double_opt_in_email_uuid' => $audience->doubleOptInEmail?->uuid,
            'avatar' => $audience->avatar,
            'from_name' => $audience->from_name,
            'from_address' => $audience->from_address,
            'reply_to' => $audience->reply_to,
            'notification_email' => $audience->notification_email,
            'subscribed_url' => $audience->subscribed_url,
            'already_subscribed_url' => $audience->already_subscribed_url,
            'unsubscribed_url' => $audience->unsubscribed_url,
        ];
    }

    private function legacyTabRedirect(Request $request, Team $currentTeam, Audience $audience): ?RedirectResponse
    {
        return match ($request->string('tab')->toString()) {
            'attributes' => to_route('audiences.settings.attributes', [
                'current_team' => $currentTeam,
                'audience' => $audience,
            ]),
            'landing-pages' => to_route('audiences.settings.landing-pages', [
                'current_team' => $currentTeam,
                'audience' => $audience,
            ]),
            default => null,
        };
    }
}
