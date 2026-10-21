<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\AddContactToAudiences;
use App\Actions\Automations\DispatchTaggedTriggers;
use App\Concerns\ReadsQueryFilters;
use App\Concerns\SearchesText;
use App\Enums\AutomationTrigger;
use App\Enums\ContactCompanyAssignmentMode;
use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Http\Requests\BulkContactRequest;
use App\Http\Requests\BulkContactTagsRequest;
use App\Http\Requests\DeleteContactRequest;
use App\Http\Requests\LookupContactRequest;
use App\Http\Requests\SaveContactRequest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\AudienceOptOut;
use App\Models\AutomationRun;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailDelivery;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Services\ManageContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    use ReadsQueryFilters;
    use SearchesText;

    public function index(Request $request, Team $currentTeam): Response|RedirectResponse
    {
        Gate::authorize('viewAny', [Contact::class, $currentTeam]);
        $filters = $this->indexFilters($request, $currentTeam);

        $contacts = $currentTeam->contacts()
            ->with(['company:id,uuid,name', 'tags:id,uuid,name,color'])
            ->withCount('subscribers')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $this->wherePersonContains($query, $filters['search']);
            })
            ->when($filters['tag'] !== '', fn ($query) => $query->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery->where('tags.uuid', $filters['tag']),
            ))
            ->when($filters['company'] === 'assigned', fn ($query) => $query->whereNotNull('company_id'))
            ->when($filters['company'] === 'unassigned', fn ($query) => $query->whereNull('company_id'))
            ->when(
                $filters['company'] !== '' && ! in_array($filters['company'], ['assigned', 'unassigned'], true),
                fn ($query) => $query->whereHas('company', fn ($companyQuery) => $companyQuery->where('uuid', $filters['company'])),
            )
            // Audience and language must hold on the same membership.
            ->when($filters['audience'] !== '' || $filters['language'] !== '', fn ($query) => $query->whereHas(
                'subscribers',
                fn ($subscriberQuery) => $subscriberQuery
                    ->when($filters['audience'] !== '', fn ($membership) => $membership->whereHas(
                        'audience',
                        fn ($audienceQuery) => $audienceQuery->where('uuid', $filters['audience']),
                    ))
                    ->when($filters['language'] !== '', fn ($membership) => $membership->where('language', $filters['language'])),
            ));

        $contacts = match ($filters['sort']) {
            'oldest' => $contacts->oldest('created_at')->orderBy('id'),
            'name' => $contacts->orderByName(),
            default => $contacts->latest()->orderByDesc('id'),
        };
        $contacts = $contacts->paginate(20)->withQueryString();

        // A bulk delete or a filter change can leave the page past the end.
        if ($contacts->isEmpty() && $contacts->currentPage() > 1) {
            return redirect($contacts->url($contacts->lastPage()));
        }

        return Inertia::render('contacts/index', [
            'contacts' => $contacts->through(fn (Contact $contact): array => $this->summary($contact)),
            'companies' => $currentTeam->companies()->alphabetical()->get(['uuid', 'name']),
            'audiences' => $currentTeam->audiences()->orderBy('name')->get(['uuid', 'name']),
            'addableAudiences' => $this->addableAudiences($currentTeam),
            'canAddToAudiences' => Gate::allows('create', [Audience::class, $currentTeam]),
            'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
            'languages' => SubscriberLanguage::options(),
            'filters' => $filters,
            'canManage' => Gate::allows('create', [Contact::class, $currentTeam]),
        ]);
    }

    public function lookup(LookupContactRequest $request, Team $currentTeam): JsonResponse
    {
        $contact = $currentTeam->contacts()
            ->with([
                'company:id,uuid,name',
                'tags:id,uuid,name,color',
                'subscribers.audience:id,uuid,name',
            ])
            ->where('email', $request->validated('email'))
            ->first();

        if (! $contact instanceof Contact) {
            return response()->json(['contact' => null]);
        }

        return response()->json([
            'contact' => [
                'uuid' => $contact->uuid,
                'avatar' => $contact->avatar,
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company' => $contact->company ? [
                    'uuid' => $contact->company->uuid,
                    'name' => $contact->company->name,
                ] : null,
                'tags' => array_values($contact->tags->map(fn (Tag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all()),
                'memberships' => $contact->subscribers
                    ->map(fn (Subscriber $subscriber): array => [
                        'audience' => [
                            'uuid' => $subscriber->audience->uuid,
                            'name' => $subscriber->audience->name,
                        ],
                        'status' => $subscriber->status->value,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(
        SaveContactRequest $request,
        Team $currentTeam,
        ManageContact $manageContact,
        AddContactToAudiences $addContactToAudiences,
        DispatchTaggedTriggers $dispatchTaggedTriggers,
    ): RedirectResponse {
        Gate::authorize('create', [Contact::class, $currentTeam]);
        $audienceUuids = $request->validated('audience_uuids', []);
        $audiences = $currentTeam->audiences()
            ->whereIn('uuid', $audienceUuids)
            ->get();

        foreach ($audiences as $audience) {
            Gate::authorize('create', [Subscriber::class, $audience]);
        }

        [$contact, $changedMemberships, $keptOptOuts] = DB::transaction(function () use (
            $request,
            $currentTeam,
            $manageContact,
            $addContactToAudiences,
            $dispatchTaggedTriggers,
            $audienceUuids,
            $audiences,
        ): array {
            [$contact, $addedTags] = $this->saveContact($request, $currentTeam, $manageContact);

            if (! $contact->wasRecentlyCreated && $audienceUuids === []) {
                throw ValidationException::withMessages([
                    'email' => __('This contact already exists. Select at least one audience to continue.'),
                ]);
            }

            $keptOptOuts = $audiences
                ->filter(fn (Audience $audience): bool => AudienceOptOut::existsFor($audience->id, $contact->email))
                ->count();
            $changedMemberships = $addContactToAudiences->handle($contact, $audiences, $request->ip());
            $dispatchTaggedTriggers->handle($contact, $addedTags);

            return [$contact, $changedMemberships, $keptOptOuts];
        });

        if ($keptOptOuts > 0) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('This person unsubscribed from :count of the selected audiences before, so they stay unsubscribed there. Resubscribe them only if they consented again.', ['count' => $keptOptOuts])]);

            return back();
        }

        $message = match (true) {
            $contact->wasRecentlyCreated && $changedMemberships > 0 => __('Contact created and added to the selected audiences.'),
            $contact->wasRecentlyCreated => __('Contact created.'),
            $changedMemberships > 0 => __('Contact added to the selected audiences.'),
            default => __('Contact already belongs to the selected audiences.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function show(Request $request, Team $currentTeam, Contact $contact): Response
    {
        Gate::authorize('view', $contact);
        $contact->load([
            'company:id,uuid,name',
            'tags:id,uuid,name,color',
            'subscribers.audience.audienceAttributes',
            'subscribers.audience:id,uuid,name,team_id',
            'subscribers.audience.team',
            'subscribers.segments:id,uuid,name',
            'subscribers.subscribeForm:id,uuid,name,deleted_at',
        ]);
        $memberships = $contact->subscribers
            ->sortByDesc('created_at')
            ->values();
        $activity = $this->activity($contact);
        $selectedAudienceUuid = $this->queryFilter($request, 'audience');

        if (! $memberships->contains(
            fn (Subscriber $subscriber): bool => $subscriber->audience->uuid === $selectedAudienceUuid,
        )) {
            $selectedAudienceUuid = null;
        }

        return Inertia::render('contacts/show', [
            'contact' => [
                ...$this->summary($contact),
                'company_assignment_mode' => $contact->company_assignment_mode->value,
                'memberships' => $memberships
                    ->toBase()
                    ->map(fn (Subscriber $subscriber): array => [
                        'uuid' => $subscriber->uuid,
                        'audience' => [
                            'uuid' => $subscriber->audience->uuid,
                            'name' => $subscriber->audience->name,
                        ],
                        'status' => $subscriber->status->value,
                        'source' => $subscriber->source->value,
                        'language' => $subscriber->language?->value,
                        'language_name' => $subscriber->language?->label(),
                        'can_manage' => Gate::allows('update', $subscriber),
                        'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
                        'pending_confirmation' => $subscriber->isPendingConfirmation() || $subscriber->isPendingResubscription(),
                        'source_form' => $subscriber->subscribeForm ? [
                            'uuid' => $subscriber->subscribeForm->uuid,
                            'name' => $subscriber->subscribeForm->name,
                        ] : null,
                        'unsubscribed_at' => $subscriber->unsubscribed_at?->toISOString(),
                        'consent_text' => $subscriber->consent_text,
                        'consented_at' => $subscriber->consented_at?->toISOString(),
                        'consent_ip' => $subscriber->consent_ip,
                        'attributes' => $subscriber->audience->audienceAttributes
                            ->sortBy('position')
                            ->toBase()
                            ->map(fn (AudienceAttribute $attribute): array => [
                                'uuid' => $attribute->uuid,
                                'name' => $attribute->name,
                                'key' => $attribute->key,
                                'type' => $attribute->type->value,
                                'required' => $attribute->required,
                                'value' => ($subscriber->attribute_values ?? [])[$attribute->key] ?? null,
                            ])
                            ->values()
                            ->all(),
                        'segments' => $subscriber->segments
                            ->sortBy('name')
                            ->toBase()
                            ->map(fn (Segment $segment): array => [
                                'uuid' => $segment->uuid,
                                'name' => $segment->name,
                            ])
                            ->values()
                            ->all(),
                    ])->all(),
                'deliveries' => $contact->deliveries()
                    ->with([
                        'email' => fn ($query) => $query->withTrashed()->select('id', 'uuid', 'name', 'deleted_at'),
                        'subscriber.audience:id,uuid,name',
                    ])
                    ->latest('sent_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (EmailDelivery $delivery): array => [
                        'uuid' => $delivery->uuid,
                        'campaign' => [
                            'uuid' => $delivery->email->deleted_at === null
                                ? $delivery->email->uuid
                                : null,
                            'name' => $delivery->email->name,
                        ],
                        'audience' => $delivery->subscriber?->audience ? [
                            'uuid' => $delivery->subscriber->audience->uuid,
                            'name' => $delivery->subscriber->audience->name,
                        ] : null,
                        'status' => $delivery->status->value,
                        'opens' => $delivery->opens_count,
                        'clicks' => $delivery->clicks_count,
                        'sent_at' => $delivery->sent_at?->toISOString(),
                    ])->all(),
            ],
            'activity' => $activity,
            'automations' => AutomationRun::query()
                ->whereIn('subscriber_id', $memberships->modelKeys())
                ->with([
                    'automation' => fn ($query) => $query->withTrashed()->select('id', 'uuid', 'name', 'deleted_at'),
                    'subscriber.audience:id,uuid,name',
                ])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (AutomationRun $run): array => [
                    'uuid' => $run->uuid,
                    'name' => $run->automation->name,
                    'automation_uuid' => $run->automation->deleted_at === null
                        ? $run->automation->uuid
                        : null,
                    'status' => $run->status->value,
                    'started_at' => $run->started_at?->toISOString(),
                    'completed_at' => $run->completed_at?->toISOString(),
                    'audience' => [
                        'uuid' => $run->subscriber->audience->uuid,
                        'name' => $run->subscriber->audience->name,
                    ],
                ])->all(),
            'selectedAudienceUuid' => $selectedAudienceUuid,
            // Only the contact dialog needs these; it loads them when it opens.
            'companies' => Inertia::optional(fn () => $currentTeam->companies()->alphabetical()->get(['uuid', 'name'])),
            'audiences' => $this->addableAudiences($currentTeam),
            'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
            'canManage' => Gate::allows('update', $contact),
            'canDelete' => Gate::allows('delete', $contact),
        ]);
    }

    /** @return array{audiences: int, subscribed: int, received: int, opened: int, clicked: int} */
    private function activity(Contact $contact): array
    {
        $stats = $contact->deliveries()
            // Only deliveries handed to the recipient's mail server count as
            // received: queued, bounced, rejected, failed and stopped ones do not.
            ->whereIn('status', [
                EmailDeliveryStatus::Sent,
                EmailDeliveryStatus::Delivered,
                EmailDeliveryStatus::Delayed,
                EmailDeliveryStatus::Complained,
            ])
            ->toBase()
            ->selectRaw('count(*) as received')
            ->selectRaw('sum(case when opens_count > 0 then 1 else 0 end) as opened')
            ->selectRaw('sum(case when clicks_count > 0 then 1 else 0 end) as clicked')
            ->first();

        return [
            'audiences' => $contact->subscribers->count(),
            'subscribed' => $contact->subscribers
                ->where('status', SubscriberStatus::Subscribed)
                ->count(),
            'received' => (int) ($stats->received ?? 0),
            'opened' => (int) ($stats->opened ?? 0),
            'clicked' => (int) ($stats->clicked ?? 0),
        ];
    }

    public function update(SaveContactRequest $request, Team $currentTeam, Contact $contact, ManageContact $manageContact, DispatchTaggedTriggers $dispatchTaggedTriggers): RedirectResponse
    {
        Gate::authorize('update', $contact);
        // The profile, its subscriber copies, tags and triggers save together,
        // as they do on create, so a failure never leaves half an edit.
        DB::transaction(function () use ($request, $currentTeam, $manageContact, $contact, $dispatchTaggedTriggers): void {
            [$contact, $addedTags] = $this->saveContact($request, $currentTeam, $manageContact, $contact);
            $dispatchTaggedTriggers->handle($contact, $addedTags);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }

    public function destroy(DeleteContactRequest $request, Team $currentTeam, Contact $contact): RedirectResponse
    {
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('contacts.index', $currentTeam);
    }

    public function bulkUnsubscribe(BulkContactRequest $request, Team $currentTeam): RedirectResponse
    {
        $memberships = Subscriber::query()
            ->with('audience.team')
            ->whereIn('contact_id', $currentTeam->contacts()->whereIn('uuid', $request->validated('ids'))->select('id'))
            ->get();
        $memberships->each(fn (Subscriber $subscriber) => Gate::authorize('update', $subscriber));

        $subscribers = $memberships
            ->where('status', SubscriberStatus::Subscribed)
            ->values();

        Subscriber::query()
            ->whereIn('id', $subscribers->modelKeys())
            ->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ]);

        Subscriber::query()
            ->whereIn('id', $memberships->modelKeys())
            ->whereNotNull('resubscribe_requested_at')
            ->update(['resubscribe_requested_at' => null]);

        foreach ($subscribers as $subscriber) {
            $subscriber->status = SubscriberStatus::Unsubscribed;
            $subscriber->setAttribute('unsubscribed_at', now());
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected contacts unsubscribed from every audience.')]);

        return back();
    }

    /**
     * Tags belong to the contact, so adding or removing one applies in every
     * audience the contact is subscribed to.
     */
    public function bulkTags(BulkContactTagsRequest $request, Team $currentTeam, ManageContact $manageContact, DispatchTaggedTriggers $dispatchTaggedTriggers): RedirectResponse
    {
        /** @var list<string> $names */
        $names = $request->validated('tags');
        $contacts = $currentTeam->contacts()
            ->whereIn('uuid', $request->validated('ids'))
            ->get();

        if ($request->validated('action') === 'add') {
            foreach ($contacts as $contact) {
                $dispatchTaggedTriggers->handle($contact, $manageContact->addTags($currentTeam, $contact, $names));
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags added to selected contacts.')]);

            return back();
        }

        foreach ($contacts as $contact) {
            $manageContact->removeTags($currentTeam, $contact, $names);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags removed from selected contacts.')]);

        return back();
    }

    public function bulkDestroy(BulkContactRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->contacts()
            ->whereIn('uuid', $request->validated('ids'))
            ->get()
            ->each(fn (Contact $contact): ?bool => $contact->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected contacts deleted.')]);

        return back();
    }

    /** @return array{uuid: string, email: string, first_name: string|null, last_name: string|null, avatar: string, company: array{uuid: string, name: string}|null, tags: list<array{uuid: string, name: string, color: string|null}>, audiences_count: int, created_at: string|null} */
    private function summary(Contact $contact): array
    {
        return [
            'uuid' => $contact->uuid,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'company_assignment_mode' => $contact->company_assignment_mode->value,
            'avatar' => $contact->avatar,
            'company' => $contact->company ? ['uuid' => $contact->company->uuid, 'name' => $contact->company->name] : null,
            'tags' => array_values($contact->tags->map(fn (Tag $tag): array => [
                'uuid' => $tag->uuid,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all()),
            'audiences_count' => $contact->subscribers_count ?? $contact->subscribers()->count(),
            'created_at' => $contact->created_at?->toISOString(),
        ];
    }

    /**
     * Get the audiences the current user may add contacts to.
     *
     * Adding subscribers is granted per workspace, so one audience's answer
     * holds for all of them and the busy contact pages check it only once.
     *
     * @return list<array{uuid: string, name: string}>
     */
    private function addableAudiences(Team $team): array
    {
        $audiences = $team->audiences()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'team_id']);
        $firstAudience = $audiences->first()?->setRelation('team', $team);

        if ($firstAudience === null || Gate::denies('create', [Subscriber::class, $firstAudience])) {
            return [];
        }

        return array_values($audiences
            ->map(fn (Audience $audience): array => ['uuid' => $audience->uuid, 'name' => $audience->name])
            ->all());
    }

    /** @return array{search: string, company: string, audience: string, tag: string, language: string, sort: string} */
    private function indexFilters(Request $request, Team $team): array
    {
        $company = $this->queryFilter($request, 'company');
        $company = Str::isUuid($company) && ! $team->companies()->where('uuid', $company)->exists() ? '' : $company;
        $audience = $this->queryFilter($request, 'audience');
        $tag = $this->queryFilter($request, 'tag');
        $sort = $this->queryFilter($request, 'sort');

        return [
            'search' => $this->queryFilter($request, 'search'),
            'company' => $company === 'assigned' || $company === 'unassigned' || Str::isUuid($company) ? $company : '',
            'audience' => Str::isUuid($audience) ? $audience : '',
            'tag' => Str::isUuid($tag) ? Str::lower($tag) : '',
            'language' => SubscriberLanguage::tryFrom($this->queryFilter($request, 'language'))?->value ?? '',
            'sort' => in_array($sort, ['oldest', 'name'], true) ? $sort : 'newest',
        ];
    }

    /** @return array{0: Contact, 1: list<string>} */
    private function saveContact(SaveContactRequest $request, Team $team, ManageContact $manageContact, ?Contact $contact = null): array
    {
        $attributes = $request->validated();
        $mode = ContactCompanyAssignmentMode::from($attributes['company_assignment_mode']);
        $companyUuid = $mode === ContactCompanyAssignmentMode::Manual ? ($attributes['company_uuid'] ?? null) : null;
        $companyId = $companyUuid !== null ? $team->companies()->where('uuid', $companyUuid)->value('id') : null;

        if ($companyUuid !== null && $companyId === null) {
            throw ValidationException::withMessages([
                'company_uuid' => __('That company no longer exists. Choose another company.'),
            ]);
        }

        $profile = [
            'email' => $request->string('email')->toString(),
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'company_assignment_mode' => $mode,
            'company_id' => $companyId,
        ];

        $creating = $contact === null;
        $contact = $creating
            ? $manageContact->findOrCreate($team, $profile)
            : $manageContact->update($contact, $profile);

        $addedTags = [];

        if (! $creating || $contact->wasRecentlyCreated) {
            $addedTags = $manageContact->syncTags($team, $contact, $attributes['tags'] ?? []);
        }

        return [$contact, $addedTags];
    }
}
