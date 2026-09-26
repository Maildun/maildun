<?php

namespace App\Http\Controllers;

use App\Actions\Emails\BuildCampaignInsights;
use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Emails\RetryEmailDeliveries;
use App\Actions\Emails\StartEmailSend;
use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailEditor;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
use App\Http\Requests\SendTestEmailRequest;
use App\Http\Requests\StoreEmailRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Jobs\SendCampaignTestEmail;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\EmailAddressHealth;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailLinkTrackingAggregate;
use App\Models\EmailTemplate;
use App\Models\EmailTrackingAggregate;
use App\Models\Media;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Email::class, $currentTeam]);

        $filters = $this->indexFilters($request);

        $emails = $currentTeam->emails()
            ->select([
                'id',
                'uuid',
                'name',
                'subject',
                'editor',
                'audience_id',
                'segment_id',
                'last_tested_at',
                'status',
                'recipient_count',
                'send_started_at',
                'sent_at',
                'updated_at',
            ])
            ->with([
                'audience' => function (Relation $query) use ($currentTeam): void {
                    $query
                        ->select(['id', 'uuid', 'name'])
                        ->withCount($this->subscribedCount($currentTeam))
                        ->with(['subscribers' => function (Relation $query) use ($currentTeam): void {
                            $this->limitToRecipientPreview($query, $currentTeam);
                        }]);
                },
                'segment' => function (Relation $query) use ($currentTeam): void {
                    $query
                        ->select(['id', 'audience_id', 'uuid', 'name'])
                        ->withCount($this->subscribedCount($currentTeam))
                        ->with(['subscribers' => function (Relation $query) use ($currentTeam): void {
                            $this->limitToRecipientPreview($query, $currentTeam);
                        }]);
                },
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->whereAny(['name', 'subject'], 'like', $search);
            })
            ->when($filters['status'] !== '', function ($query) use ($filters): void {
                $this->applyCampaignStatusFilter($query, $filters['status']);
            })
            ->when($filters['editor'] !== '', fn ($query) => $query->where('editor', $filters['editor']))
            ->when($filters['audience'] !== '', function ($query) use ($filters, $currentTeam): void {
                $query->where(
                    'audience_id',
                    $this->resolveAudienceId($currentTeam, $filters['audience']) ?? 0,
                );
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Email $email): array {
                $recipientSource = $email->segment ?? $email->audience;
                $status = $email->status === EmailStatus::Draft && $email->sent_at
                    ? EmailStatus::Sent
                    : $email->status;

                return [
                    'uuid' => $email->uuid,
                    'name' => $email->name,
                    'subject' => $email->subject,
                    'editor' => $email->editor->value,
                    'status' => $status->value,
                    'audience' => $email->audience ? [
                        'uuid' => $email->audience->uuid,
                        'name' => $email->audience->name,
                    ] : null,
                    'segment' => $email->segment ? [
                        'uuid' => $email->segment->uuid,
                        'name' => $email->segment->name,
                    ] : null,
                    'recipient_count' => $status === EmailStatus::Draft || $email->recipient_count === 0
                        ? ($recipientSource->subscribed_count ?? 0)
                        : $email->recipient_count,
                    'recipients' => $recipientSource
                        ? $recipientSource->subscribers->map(fn (Subscriber $subscriber): array => [
                            'uuid' => $subscriber->uuid,
                            'avatar' => $subscriber->avatar,
                            'email' => $subscriber->email,
                        ])->values()->all()
                        : [],
                    'last_tested_at' => $email->last_tested_at?->toISOString(),
                    'updated_at' => $email->updated_at?->toISOString(),
                ];
            });

        return Inertia::render('emails/index', [
            'emails' => $emails,
            'templates' => $this->templatesFor($currentTeam),
            'audiences' => $currentTeam->audiences()
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn (Audience $audience): array => [
                    'uuid' => $audience->uuid,
                    'name' => $audience->name,
                ])
                ->values()
                ->all(),
            'filters' => $filters,
            'defaultEditor' => $currentTeam->email_editor->value,
            'canManage' => Gate::allows('create', [Email::class, $currentTeam]),
        ]);
    }

    public function store(StoreEmailRequest $request, Team $currentTeam): RedirectResponse
    {
        $template = $request->filled('template')
            ? EmailTemplate::query()
                ->availableTo($currentTeam)
                ->where('uuid', $request->string('template'))
                ->first()
            : null;

        $editor = $currentTeam->email_editor;

        $body = $template
            ? ['html' => $template->html, 'source' => $template->source, 'design' => $template->design]
            : EmailTemplate::blankBodyFor($editor);

        $email = $currentTeam->emails()->create([
            'email_template_id' => $template?->id,
            'name' => $request->string('name'),
            'subject' => filled($template?->subject)
                ? $template->subject
                : $request->string('name')->value(),
            'preheader' => $template?->preheader,
            'editor' => $editor,
            'html' => $body['html'],
            'source' => $body['source'],
            'design' => $body['design'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('emails.edit', ['current_team' => $currentTeam, 'email' => $email]);
    }

    public function edit(Team $currentTeam, Email $email): Response|RedirectResponse
    {
        Gate::authorize('view', $email);

        if ($email->status !== EmailStatus::Draft) {
            return to_route('emails.show', [$currentTeam, $email]);
        }

        $email->loadMissing([
            'audience:id,uuid,name',
            'segment:id,uuid,name',
            'attachments:id,uuid,email_id,original_name,mime_type,size',
        ]);

        $senders = $currentTeam->verifiedSenders()
            ->orderByDesc('email_verified_at')
            ->oldest('id')
            ->get();

        $selectedSenderUuid = null;

        if ($email->from_name === null && $email->from_address === null && $email->reply_to === null) {
            $selectedSenderUuid = UpdateEmailRequest::AUDIENCE_DEFAULT_SENDER;
        } elseif ($email->from_address !== null) {
            $selectedSenderUuid = $senders
                ->first(fn (TeamSender $sender): bool => strcasecmp($sender->email, trim($email->from_address)) === 0)
                ?->uuid;
        }

        if ($selectedSenderUuid === null
            && ($email->from_name !== null || $email->from_address !== null || $email->reply_to !== null)) {
            $selectedSenderUuid = UpdateEmailRequest::CURRENT_SENDER;
        }

        return Inertia::render('emails/edit', [
            'email' => [
                'uuid' => $email->uuid,
                'name' => $email->name,
                'subject' => $email->subject,
                'preheader' => $email->preheader,
                'from_name' => $email->from_name,
                'from_address' => $email->from_address,
                'reply_to' => $email->reply_to,
                'editor' => $currentTeam->email_editor->value,
                'html' => $email->html ?? '',
                'source' => $email->source ?? ($currentTeam->email_editor->usesSource() ? ($email->html ?? '') : ''),
                'plain_text' => $email->plain_text ?? '',
                'query_string' => $email->query_string ?? '',
                'track_clicks' => $email->track_clicks,
                'track_opens' => $email->track_opens,
                'design' => $email->design,
                'audience' => $email->audience?->uuid,
                'segment' => $email->segment?->uuid,
                'last_tested_at' => $email->last_tested_at?->toISOString(),
                'updated_at' => $email->updated_at?->toISOString(),
                'attachments' => $email->attachments->map(fn ($attachment): array => [
                    'uuid' => $attachment->uuid,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'size_label' => Number::fileSize($attachment->size),
                ])->values(),
            ],
            'audiences' => $currentTeam->audiences()
                ->withCount($this->subscribedCount($currentTeam))
                ->with([
                    'audienceAttributes' => fn (Relation $query) => $query
                        ->select(['id', 'audience_id', 'name', 'key'])
                        ->orderBy('position'),
                    'segments' => fn (Relation $query) => $query
                        ->select(['id', 'audience_id', 'uuid', 'name'])
                        ->withCount($this->subscribedCount($currentTeam))
                        ->orderByRaw('LOWER(name)'),
                ])
                ->orderByRaw('LOWER(name)')
                ->get()
                ->map(fn (Audience $audience) => [
                    'uuid' => $audience->uuid,
                    'name' => $audience->name,
                    'from_name' => $audience->from_name,
                    'from_address' => $audience->from_address,
                    'reply_to' => $audience->reply_to,
                    'subscribed_count' => $audience->subscribed_count,
                    'attributes' => $audience->audienceAttributes
                        ->map(fn (AudienceAttribute $attribute): array => [
                            'name' => $attribute->name,
                            'key' => $attribute->key,
                        ])
                        ->values(),
                    'segments' => $audience->segments
                        ->map(fn (Segment $segment) => [
                            'uuid' => $segment->uuid,
                            'name' => $segment->name,
                            'subscribed_count' => $segment->subscribed_count,
                        ])
                        ->values(),
                ]),
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
            'defaults' => [
                'from_name' => $currentTeam->email_from_name ?? config('mail.from.name'),
                'from_address' => $currentTeam->email_from_address ?? config('mail.from.address'),
                'reply_to' => $currentTeam->email_reply_to,
            ],
            'canManage' => Gate::allows('update', $email),
            'mediaLibrary' => fn (): ?array => $currentTeam->email_editor === EmailEditor::Builder
                ? $this->mediaLibraryFor($currentTeam)
                : null,
        ]);
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     canManage: bool,
     *     canUpload: bool,
     *     atLimit: bool,
     *     convertUploadsToWebp: bool,
     *     hasMore: bool
     * }
     */
    private function mediaLibraryFor(Team $team): array
    {
        Gate::authorize('viewAny', [Media::class, $team]);

        $media = $team->media()
            ->with(['category', 'tags'])
            ->latest()
            ->limit(61)
            ->get();
        $canManage = Gate::allows('create', [Media::class, $team]);

        return [
            'items' => array_values($media
                ->take(60)
                ->map(fn (Media $item): array => $item->toInertia())
                ->all()),
            'canManage' => $canManage,
            'canUpload' => $canManage,
            'atLimit' => false,
            'convertUploadsToWebp' => $team->convert_uploads_to_webp,
            'hasMore' => $media->count() > 60,
        ];
    }

    public function update(UpdateEmailRequest $request, Team $currentTeam, Email $email): RedirectResponse
    {
        abort_unless($email->status === EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));

        $editor = $currentTeam->email_editor;
        $senderAttributes = [];

        if ($request->has('sender_uuid')) {
            $senderUuid = $request->validated('sender_uuid');

            if ($senderUuid === UpdateEmailRequest::CURRENT_SENDER) {
                $senderAttributes = [];
            } elseif ($senderUuid === UpdateEmailRequest::AUDIENCE_DEFAULT_SENDER) {
                $senderAttributes = [
                    'from_name' => null,
                    'from_address' => null,
                    'reply_to' => null,
                ];
            } else {
                $sender = $currentTeam->verifiedSenders()
                    ->where('uuid', $senderUuid)
                    ->firstOrFail();

                $senderAttributes = [
                    'from_name' => $sender->name,
                    'from_address' => $sender->email,
                    'reply_to' => $sender->reply_to,
                ];
            }
        }

        $email->update([
            ...$request->safe()->only([
                'name',
                'subject',
                'preheader',
                'html',
                'source',
                'plain_text',
                'query_string',
                'track_clicks',
                'track_opens',
            ]),
            ...$senderAttributes,
            'editor' => $editor,
            'source' => $editor->usesSource() ? $request->input('source') : null,
            'plain_text' => $editor === EmailEditor::PlainText
                ? $request->input('source')
                : $request->input('plain_text'),
            // HTML emails keep the rendered markup but do not store a block document.
            'design' => $editor === EmailEditor::Builder ? $request->input('design') : null,
            'audience_id' => $this->resolveAudienceId($currentTeam, $request->input('audience')),
            'segment_id' => $this->resolveSegmentId($currentTeam, $request->input('segment')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign saved.')]);

        return back();
    }

    public function previewAndSend(
        Team $currentTeam,
        Email $email,
        BuildTrackedEmailHtml $trackedHtml,
        RenderCampaignContent $renderer,
    ): Response|RedirectResponse {
        Gate::authorize('send', $email);

        if ($email->status !== EmailStatus::Draft) {
            return to_route('emails.show', [$currentTeam, $email]);
        }

        if ($email->audience_id === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Choose recipients before previewing this campaign.'),
            ]);

            return to_route('emails.edit', [$currentTeam, $email]);
        }

        $recipientQuery = $this->composePreviewRecipients($email);
        $recipient = (clone $recipientQuery)
            ->orderBy('subscribers.email')
            ->orderBy('subscribers.id')
            ->first();

        if (! $recipient) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This campaign has no subscribed recipients.'),
            ]);

            return to_route('emails.edit', [$currentTeam, $email]);
        }

        return Inertia::render('emails/preview-and-send', [
            'campaign' => [
                'uuid' => $email->uuid,
                'name' => $email->name,
            ],
            'recipientCount' => (clone $recipientQuery)->count(),
            'suppressedRecipients' => $this->suppressedRecipients($email),
            'unconfirmedRecipients' => $this->campaignSubscribers($email)
                ->where('subscribers.status', SubscriberStatus::Subscribed)
                ->whereNull('subscribers.subscribed_at')
                ->count(),
            'missingUnsubscribe' => ! $trackedHtml->authorPlacedUnsubscribe($email->html ?? ''),
            'preview' => $this->composePreviewPayload(
                $email,
                $recipient,
                $recipientQuery,
                '',
                $trackedHtml,
                $renderer,
            ),
        ]);
    }

    public function composePreview(
        Request $request,
        Team $currentTeam,
        Email $email,
        BuildTrackedEmailHtml $trackedHtml,
        RenderCampaignContent $renderer,
    ): JsonResponse {
        Gate::authorize('send', $email);
        abort_unless($email->status === EmailStatus::Draft, 409, __('A queued campaign can no longer be previewed.'));

        $validated = $request->validate([
            'recipient' => ['nullable', 'string', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        if ($email->audience_id === null) {
            throw ValidationException::withMessages([
                'email' => __('Choose recipients before previewing this campaign.'),
            ]);
        }

        $recipientQuery = $this->composePreviewRecipients($email);
        $recipient = filled($validated['recipient'] ?? null)
            ? (clone $recipientQuery)->where('uuid', $validated['recipient'])->firstOrFail()
            : (clone $recipientQuery)->orderBy('email')->orderBy('id')->first();

        if (! $recipient) {
            throw ValidationException::withMessages([
                'email' => __('This campaign has no subscribed recipients.'),
            ]);
        }

        return response()->json($this->composePreviewPayload(
            $email,
            $recipient,
            $recipientQuery,
            Str::of($validated['q'] ?? '')->trim()->toString(),
            $trackedHtml,
            $renderer,
        ));
    }

    public function sendTest(
        SendTestEmailRequest $request,
        Team $currentTeam,
        Email $email,
        BuildTrackedEmailHtml $trackedHtml,
        RenderCampaignContent $renderer,
    ): RedirectResponse {
        abort_unless($email->status === EmailStatus::Draft, 409, __('A queued campaign can no longer send test copies.'));

        $mergeData = $renderer->testData($request->string('to')->value(), now());

        SendCampaignTestEmail::dispatch(
            $email->id,
            $request->string('to')->value(),
            $renderer->text($email->subject, $mergeData),
            $trackedHtml->prepareTestHtml(
                $email,
                $renderer->html($email->html ?? '', $mergeData),
            ),
            $renderer->plainText($email, $mergeData),
        )->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Test email queued for :address.', ['address' => $request->string('to')->value()]),
        ]);

        return back();
    }

    public function send(Team $currentTeam, Email $email, StartEmailSend $startEmailSend): RedirectResponse
    {
        Gate::authorize('send', $email);
        $startEmailSend->handle($email);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign queued for delivery.')]);

        return to_route('emails.show', [$currentTeam, $email]);
    }

    public function retry(Request $request, Team $currentTeam, Email $email, RetryEmailDeliveries $retry): RedirectResponse
    {
        Gate::authorize('send', $email);
        $retry->handle($email, includeUnconfirmed: $request->boolean('include_unconfirmed'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Failed deliveries queued again.')]);

        return back();
    }

    public function retryDelivery(Request $request, Team $currentTeam, Email $email, EmailDelivery $delivery, RetryEmailDeliveries $retry): RedirectResponse
    {
        Gate::authorize('send', $email);
        $retry->handle($email, [$delivery->id], $request->boolean('include_unconfirmed'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery queued again.')]);

        return back();
    }

    public function show(
        Team $currentTeam,
        Email $email,
        BuildCampaignInsights $campaignInsights,
    ): Response|RedirectResponse {
        if (($redirect = $this->prepareReport($currentTeam, $email)) !== null) {
            return $redirect;
        }

        return Inertia::render('emails/show', [
            ...$this->campaignReportProps($email),
            'insights' => $campaignInsights->handle($email),
        ]);
    }

    public function recipients(Request $request, Team $currentTeam, Email $email): Response|RedirectResponse
    {
        if (($redirect = $this->prepareReport($currentTeam, $email)) !== null) {
            return $redirect;
        }

        $props = $this->campaignReportProps($email);
        $recipientFilter = $this->recipientFilter($request);

        return Inertia::render('emails/recipients', [
            ...$props,
            'filters' => [
                'status' => $recipientFilter,
            ],
            'recipients' => $this->recipientsPaginator($email, $recipientFilter, $props['canManage']),
        ]);
    }

    public function links(Team $currentTeam, Email $email): Response|RedirectResponse
    {
        if (($redirect = $this->prepareReport($currentTeam, $email)) !== null) {
            return $redirect;
        }

        return Inertia::render('emails/links', [
            ...$this->campaignReportProps($email),
            'links' => $email->links()
                ->with('trackingAggregate:id,email_link_id,total_clicks_count,unique_clicks_count')
                ->orderBy('position')
                ->get()
                ->map(function (EmailLink $link): array {
                    $trackingAggregate = $link->getRelation('trackingAggregate');

                    return [
                        'uuid' => $link->uuid,
                        'url' => $link->url,
                        'clicks' => $trackingAggregate instanceof EmailLinkTrackingAggregate
                            ? $trackingAggregate->total_clicks_count
                            : 0,
                    ];
                }),
        ]);
    }

    public function preview(Team $currentTeam, Email $email): Response|RedirectResponse
    {
        if (($redirect = $this->prepareReport($currentTeam, $email)) !== null) {
            return $redirect;
        }

        return Inertia::render('emails/preview', $this->campaignReportProps($email));
    }

    public function destroy(Team $currentTeam, Email $email): RedirectResponse
    {
        Gate::authorize('delete', $email);
        $email->attachments->each(fn ($attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        $email->attachments()->delete();
        $email->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('emails.index', ['current_team' => $currentTeam]);
    }

    private function prepareReport(Team $currentTeam, Email $email): ?RedirectResponse
    {
        Gate::authorize('view', $email);

        return $email->status === EmailStatus::Draft
            ? to_route('emails.edit', [$currentTeam, $email])
            : null;
    }

    /**
     * @return array{campaign: array<string, mixed>, metrics: array<string, int|float|string|null>, canManage: bool}
     */
    private function campaignReportProps(Email $email): array
    {
        $email->loadMissing([
            'audience:id,uuid,name',
            'segment:id,uuid,name',
            'trackingAggregate:id,email_id,unique_opens_count,unique_clicks_count',
        ]);
        $trackingAggregate = $email->getRelation('trackingAggregate');
        $deliveries = $email->deliveries();
        $recipientCount = max($email->recipient_count, 1);
        $processedCount = (clone $deliveries)->whereNotIn('status', [
            EmailDeliveryStatus::Queued,
            EmailDeliveryStatus::Sending,
        ])->count();
        $openedCount = $trackingAggregate instanceof EmailTrackingAggregate
            ? $trackingAggregate->unique_opens_count
            : 0;
        $clickedCount = $trackingAggregate instanceof EmailTrackingAggregate
            ? $trackingAggregate->unique_clicks_count
            : 0;
        $sesRecipientCount = (clone $deliveries)
            ->where('provider', EmailProvider::AmazonSes)
            ->count();
        $deliveredCount = (clone $deliveries)
            ->where('provider', EmailProvider::AmazonSes)
            ->whereNotNull('delivered_at')
            ->count();
        $deliveryFeedback = match (true) {
            $sesRecipientCount === 0 => 'unavailable',
            $sesRecipientCount < $email->recipient_count => 'partial',
            default => 'available',
        };
        $bouncedCount = (clone $deliveries)->where('status', EmailDeliveryStatus::Bounced)->count();
        $complainedCount = (clone $deliveries)->where('status', EmailDeliveryStatus::Complained)->count();
        $failedCount = (clone $deliveries)->whereIn('status', [
            EmailDeliveryStatus::Failed,
            EmailDeliveryStatus::Rejected,
        ])->count();
        $retryingCount = (clone $deliveries)
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereHas('attempts', fn (Builder $attempts) => $attempts->where('status', EmailDeliveryStatus::Failed))
            ->count();
        $retryableCount = (clone $deliveries)->retryableFor($email->team)->count();
        $unconfirmedRetryableCount = (clone $deliveries)->retryableFor($email->team)->unconfirmed()->count();
        $deliveryTransports = (clone $deliveries)
            ->select(['provider', 'uses_team_email_integration'])
            ->distinct()
            ->get();
        $hasMixedTransports = $deliveryTransports->count() > 1;

        return [
            'campaign' => [
                'uuid' => $email->uuid,
                'name' => $email->name,
                'subject' => $email->subject,
                'preheader' => $email->preheader,
                'html' => $email->html ?? '',
                'status' => $email->status->value,
                'provider' => $hasMixedTransports ? 'mixed' : $deliveryTransports->first()?->provider,
                'uses_team_email_integration' => $deliveryTransports->contains(
                    fn (EmailDelivery $delivery): bool => $delivery->uses_team_email_integration,
                ),
                'audience' => $email->audience?->name,
                'segment' => $email->segment?->name,
                'recipient_count' => $email->recipient_count,
                'send_started_at' => $email->send_started_at?->toISOString(),
                'sent_at' => $email->sent_at?->toISOString(),
            ],
            'metrics' => [
                'processed' => $processedCount,
                'progress' => (int) round(($processedCount / $recipientCount) * 100),
                'delivered' => $deliveredCount,
                'opened' => $openedCount,
                'clicked' => $clickedCount,
                'bounced' => $bouncedCount,
                'complained' => $complainedCount,
                'failed' => $failedCount,
                'retrying' => $retryingCount,
                'retryable' => $retryableCount,
                'unconfirmed' => $unconfirmedRetryableCount,
                'delivery_feedback' => $deliveryFeedback,
                'feedback_recipient_count' => $sesRecipientCount,
                'delivery_rate' => $sesRecipientCount === 0
                    ? null
                    : round(($deliveredCount / $sesRecipientCount) * 100, 1),
                'open_rate' => round(($openedCount / $recipientCount) * 100, 1),
                'click_rate' => round(($clickedCount / $recipientCount) * 100, 1),
            ],
            'canManage' => Gate::allows('send', $email),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, covariant array{uuid: string, avatar: string|null, email: string, name: string|null, status: string, opens: int, clicks: int, failure_reason: string|null, sent_at: string|null, can_retry: bool, retry_blocked_reason: string|null, unconfirmed: bool}>
     */
    private function recipientsPaginator(Email $email, ?string $recipientFilter, bool $canManage): LengthAwarePaginator
    {
        $recipients = $email->deliveries()
            ->with(['subscriber:id,uuid,status,subscribed_at'])
            ->tap(fn (Builder $query) => $this->applyRecipientFilter($query, $recipientFilter, $email->team))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $page = $recipients->getCollection();
        $retryableIds = $email->status->isActive() ? [] : $email->deliveries()
            ->retryableFor($email->team)
            ->whereKey($page->modelKeys())
            ->pluck('email_deliveries.id')
            ->all();
        $suppressedAddresses = array_values(EmailAddressHealth::query()
            ->suppressedFor($email->team)
            ->whereIn('email_address_healths.email', $page->pluck('email_address'))
            ->get(['email_address_healths.email'])
            ->map(fn (EmailAddressHealth $health): string => $health->email)
            ->all());

        return $recipients->through(function (EmailDelivery $delivery) use ($canManage, $email, $retryableIds, $suppressedAddresses): array {
            $retryable = in_array($delivery->id, $retryableIds, true);

            return [
                'uuid' => $delivery->uuid,
                'avatar' => $delivery->subscriber?->avatar,
                'email' => $delivery->email_address,
                'name' => trim($delivery->first_name.' '.$delivery->last_name) ?: null,
                'status' => $delivery->status->value,
                'opens' => $delivery->opens_count,
                'clicks' => $delivery->clicks_count,
                'failure_reason' => $delivery->failure_reason,
                'sent_at' => $delivery->sent_at?->toISOString(),
                'can_retry' => $canManage && $retryable,
                'retry_blocked_reason' => $retryable
                    ? null
                    : $this->retryBlockedReason($email, $delivery, $suppressedAddresses),
                'unconfirmed' => $delivery->isUnconfirmed(),
            ];
        });
    }

    /**
     * Why a report row cannot be retried, or null when there is nothing to
     * retry. Eligibility itself comes from EmailDelivery::scopeRetryableFor;
     * this only names the check that failed.
     *
     * @param  list<string>  $suppressedAddresses
     */
    private function retryBlockedReason(Email $email, EmailDelivery $delivery, array $suppressedAddresses): ?string
    {
        if ($delivery->status === EmailDeliveryStatus::Bounced) {
            return __('Permanent bounces are never retried.');
        }

        if ($delivery->status === EmailDeliveryStatus::Complained) {
            return __('Spam complaints are never retried.');
        }

        if (! $delivery->status->isRetryable()) {
            return null;
        }

        return match (true) {
            $email->status->isActive() => __('Wait until this campaign finishes sending.'),
            $delivery->subscriber !== null
                && $delivery->subscriber->status !== SubscriberStatus::Subscribed => __('This recipient has unsubscribed.'),
            $delivery->subscriber?->isPendingConfirmation() === true => __('This recipient has not confirmed their subscription yet.'),
            in_array($delivery->email_address, $suppressedAddresses, true) => __('This address is suppressed after a permanent bounce or spam complaint.'),
            default => __('This delivery cannot be retried.'),
        };
    }

    /**
     * @return array{q: string, status: string, editor: string, audience: string}
     */
    private function indexFilters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $editor = $request->string('editor')->toString();
        $statusEnum = EmailStatus::tryFrom($status);
        $editorEnum = EmailEditor::tryFrom($editor);

        return [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $statusEnum instanceof EmailStatus ? $statusEnum->value : '',
            'editor' => $editorEnum instanceof EmailEditor ? $editorEnum->value : '',
            'audience' => $request->string('audience')->trim()->toString(),
        ];
    }

    /**
     * @param  Builder<Email>  $query
     */
    private function applyCampaignStatusFilter(Builder $query, string $status): void
    {
        if ($status === EmailStatus::Sent->value) {
            $query->where(function (Builder $inner): void {
                $inner->where('status', EmailStatus::Sent)
                    ->orWhere(function (Builder $draft): void {
                        $draft->where('status', EmailStatus::Draft)
                            ->whereNotNull('sent_at');
                    });
            });

            return;
        }

        if ($status === EmailStatus::Draft->value) {
            $query->where('status', EmailStatus::Draft)
                ->whereNull('sent_at');

            return;
        }

        $query->where('status', $status);
    }

    /**
     * The starter templates plus the team's own, ready for the compose picker.
     *
     * @return list<array{uuid: string, name: string, description: string|null, editor: 'builder'|'html'|'plain_text'|'markdown', is_starter: bool}>
     */
    protected function templatesFor(Team $team): array
    {
        return array_values(EmailTemplate::query()
            ->availableTo($team)
            ->where('editor', $team->email_editor->value)
            ->orderedForPicker()
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'uuid' => $template->uuid,
                'name' => $template->name,
                'description' => $template->description,
                'editor' => $template->editor->value,
                'is_starter' => $template->isStarter(),
            ])
            ->all());
    }

    /**
     * A withCount clause for the people an email would actually reach. Shared
     * by audiences and segments, which both count through `subscribers`, and
     * matching StartEmailSend so suppressed addresses are never counted.
     *
     * @return array<string, callable(Builder<Subscriber>): mixed>
     */
    protected function subscribedCount(Team $team): array
    {
        return [
            'subscribers as subscribed_count' => fn (Builder $query) => $query->scopes(['sendableFor' => [$team]]),
        ];
    }

    /** @return Builder<Subscriber> */
    private function composePreviewRecipients(Email $email): Builder
    {
        return $this->campaignSubscribers($email)->sendableFor($email->team);
    }

    /**
     * Everyone in the campaign's audience or segment, before any status or
     * suppression filtering.
     *
     * @return Builder<Subscriber>
     */
    private function campaignSubscribers(Email $email): Builder
    {
        return Subscriber::query()
            ->where('audience_id', $email->audience_id)
            ->when(
                $email->segment_id !== null,
                fn (Builder $query) => $query->whereHas(
                    'segments',
                    fn (Builder $segment) => $segment->whereKey($email->segment_id),
                ),
            );
    }

    /**
     * Confirmed recipients the send will skip because the workspace
     * suppressed their address, grouped by why it was suppressed.
     *
     * @return array{count: int, reasons: list<array{label: string, count: int}>}
     */
    private function suppressedRecipients(Email $email): array
    {
        $reasons = array_values(EmailAddressHealth::query()
            ->suppressedFor($email->team)
            ->whereIn(
                'email_address_healths.email',
                $this->campaignSubscribers($email)
                    ->where('subscribers.status', SubscriberStatus::Subscribed)
                    ->whereNotNull('subscribers.subscribed_at')
                    ->select('subscribers.email'),
            )
            ->toBase()
            ->selectRaw('reason, count(*) as recipients')
            ->groupBy('reason')
            ->orderByDesc('recipients')
            ->orderBy('reason')
            ->get()
            ->map(fn (object $row): array => [
                'label' => EmailAddressHealthReason::tryFrom((string) $row->reason)?->label() ?? __('Suppressed'),
                'count' => (int) $row->recipients,
            ])
            ->all());

        return [
            'count' => array_sum(array_column($reasons, 'count')),
            'reasons' => $reasons,
        ];
    }

    /** @return array{uuid: string, name: string, email: string, label: string} */
    private function composePreviewRecipient(Subscriber $subscriber): array
    {
        $name = Str::of($subscriber->first_name.' '.$subscriber->last_name)
            ->squish()
            ->toString();

        return [
            'uuid' => $subscriber->uuid,
            'name' => $name !== '' ? $name : $subscriber->email,
            'email' => $subscriber->email,
            'label' => $name !== '' ? $name.' · '.$subscriber->email : $subscriber->email,
        ];
    }

    /**
     * @param  Builder<Subscriber>  $recipientQuery
     * @return array{
     *     recipient: array{uuid: string, name: string, email: string, label: string},
     *     recipients: list<array{uuid: string, name: string, email: string, label: string}>,
     *     navigation: array{previous: ?string, next: ?string, position: int},
     *     subject: string,
     *     preheader: ?string,
     *     html: string
     * }
     */
    private function composePreviewPayload(
        Email $email,
        Subscriber $recipient,
        Builder $recipientQuery,
        string $search,
        BuildTrackedEmailHtml $trackedHtml,
        RenderCampaignContent $renderer,
    ): array {
        $beforeRecipient = function (Builder $query) use ($recipient): void {
            $query
                ->where('subscribers.email', '<', $recipient->email)
                ->orWhere(function (Builder $tie) use ($recipient): void {
                    $tie
                        ->where('subscribers.email', $recipient->email)
                        ->where('subscribers.id', '<', $recipient->id);
                });
        };
        $afterRecipient = function (Builder $query) use ($recipient): void {
            $query
                ->where('subscribers.email', '>', $recipient->email)
                ->orWhere(function (Builder $tie) use ($recipient): void {
                    $tie
                        ->where('subscribers.email', $recipient->email)
                        ->where('subscribers.id', '>', $recipient->id);
                });
        };

        $recipientOptions = array_values(
            (clone $recipientQuery)
                ->select(['subscribers.id', 'subscribers.uuid', 'subscribers.email', 'subscribers.first_name', 'subscribers.last_name'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $term = '%'.addcslashes($search, '%_\\').'%';

                    $query->whereAny(['subscribers.first_name', 'subscribers.last_name', 'subscribers.email'], 'like', $term);
                })
                ->orderBy('subscribers.email')
                ->orderBy('subscribers.id')
                ->limit(25)
                ->get()
                ->map(fn (Subscriber $subscriber): array => $this->composePreviewRecipient($subscriber))
                ->all(),
        );

        $previousRecipientUuid = (clone $recipientQuery)
            ->where($beforeRecipient)
            ->orderByDesc('subscribers.email')
            ->orderByDesc('subscribers.id')
            ->value('subscribers.uuid');
        $nextRecipientUuid = (clone $recipientQuery)
            ->where($afterRecipient)
            ->orderBy('subscribers.email')
            ->orderBy('subscribers.id')
            ->value('subscribers.uuid');
        $mergeData = $renderer->mergeData($recipient, now());

        return [
            'recipient' => $this->composePreviewRecipient($recipient),
            'recipients' => $recipientOptions,
            'navigation' => [
                'previous' => is_string($previousRecipientUuid) ? $previousRecipientUuid : null,
                'next' => is_string($nextRecipientUuid) ? $nextRecipientUuid : null,
                'position' => (clone $recipientQuery)->where($beforeRecipient)->count() + 1,
            ],
            'subject' => $renderer->text($email->subject, $mergeData),
            'preheader' => filled($email->preheader)
                ? $renderer->text((string) $email->preheader, $mergeData)
                : null,
            'html' => $trackedHtml->preparePreviewHtml(
                $renderer->html($email->html ?? '', $mergeData),
                '#unsubscribe',
                '#web-view',
                $email->query_string,
                $trackedHtml->subscribeFormUrl($email) ?? '#subscribe',
            ),
        ];
    }

    /** @param Relation<Subscriber, Model, mixed> $query */
    protected function limitToRecipientPreview(Relation $query, Team $team): void
    {
        $query
            ->select([
                'subscribers.id',
                'subscribers.uuid',
                'subscribers.audience_id',
                'subscribers.email',
            ])
            ->sendableFor($team)
            ->oldest('subscribers.id')
            ->limit(3);
    }

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    protected function applyRecipientFilter(Builder $query, ?string $filter, Team $team): Builder
    {
        return match ($filter) {
            'retryable' => $query->retryableFor($team),
            'opened' => $query->where('opens_count', '>', 0),
            'clicked' => $query->where('clicks_count', '>', 0),
            null => $query,
            default => $query->where('status', $filter),
        };
    }

    protected function recipientFilter(Request $request): ?string
    {
        $status = $request->string('status')->toString();
        $allowed = [
            'retryable',
            'opened',
            'clicked',
            ...array_column(EmailDeliveryStatus::cases(), 'value'),
        ];

        return in_array($status, $allowed, true) ? $status : null;
    }

    protected function resolveAudienceId(Team $team, ?string $uuid): ?int
    {
        if (blank($uuid)) {
            return null;
        }

        return $team->audiences()->where('uuid', $uuid)->value('id');
    }

    protected function resolveSegmentId(Team $team, ?string $uuid): ?int
    {
        if (blank($uuid)) {
            return null;
        }

        return Segment::query()
            ->where('uuid', $uuid)
            ->whereHas('audience', fn ($query) => $query->where('team_id', $team->id))
            ->value('id');
    }
}
