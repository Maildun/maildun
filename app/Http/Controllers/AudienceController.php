<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\BuildAudienceSubscriberStats;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TransactionalEmailStatus;
use App\Http\Requests\DeleteAudienceRequest;
use App\Http\Requests\SaveAudienceRequest;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AudienceController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $filters = $this->indexFilters($request);

        $query = $team->audiences()
            ->withCount([
                'subscribers',
                'subscribers as subscribed_count' => fn ($query) => $query->where('status', SubscriberStatus::Subscribed),
                'segments',
                'subscribeForms',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.str($filters['search'])->lower().'%';
                $query->where(fn ($audienceQuery) => $audienceQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search]));
            })
            ->when($filters['filter'] === 'active', fn ($query) => $query->whereHas(
                'subscribers',
                fn ($subscribers) => $subscribers->where('status', SubscriberStatus::Subscribed),
            ))
            ->when($filters['filter'] === 'empty', fn ($query) => $query->doesntHave('subscribers'))
            ->when($filters['filter'] === 'segments', fn ($query) => $query->has('segments'))
            ->when($filters['filter'] === 'forms', fn ($query) => $query->has('subscribeForms'));

        $query = match ($filters['sort']) {
            'oldest' => $query->oldest(),
            'subscribers' => $query->orderByDesc('subscribed_count')->latest(),
            default => $query->latest(),
        };

        return Inertia::render('audiences/index', [
            'audiences' => $query
                ->paginate(12)
                ->withQueryString()
                ->through(fn (Audience $audience) => [
                    'uuid' => $audience->uuid,
                    'name' => $audience->name,
                    'description' => $audience->description,
                    'avatar' => $audience->avatar,
                    'subscribers_count' => $audience->subscribers_count,
                    'subscribed_count' => $audience->subscribed_count,
                    'segments_count' => $audience->segments_count,
                    'forms_count' => $audience->subscribe_forms_count,
                    'created_at' => $audience->created_at->toISOString(),
                ]),
            'filters' => $filters,
            'hasAudiences' => $team->audiences()->exists(),
            'canManage' => Gate::allows('create', [Audience::class, $team]),
        ]);
    }

    public function store(SaveAudienceRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);
        Gate::authorize('create', [Audience::class, $team]);

        $audience = $team->audiences()->create($this->audienceAttributes($request, $team));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Audience created.')]);

        return to_route('audiences.show', ['current_team' => $team, 'audience' => $audience]);
    }

    public function show(
        Request $request,
        Team $currentTeam,
        Audience $audience,
        BuildAudienceSubscriberStats $buildSubscriberStats,
    ): Response {
        Gate::authorize('view', $audience);

        $filters = $this->subscriberFilters($request);

        $subscribers = $audience->subscribers()
            ->with(['subscribeForm:id,uuid,name,deleted_at', 'tags:id,uuid,name,color'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.mb_strtolower($filters['search']).'%';
                $query->where(fn ($subscriberQuery) => $subscriberQuery
                    ->whereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$search]));
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['source'] !== 'all', fn ($query) => $query->where('source', $filters['source']))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Subscriber $subscriber) => [
                'uuid' => $subscriber->uuid,
                'avatar' => $subscriber->avatar,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'status' => $subscriber->status->value,
                'source' => $subscriber->source->value,
                'source_form' => $subscriber->subscribeForm ? [
                    'uuid' => $subscriber->subscribeForm->uuid,
                    'name' => $subscriber->subscribeForm->name,
                ] : null,
                'tags' => array_values($subscriber->tags->map(fn (Tag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all()),
                'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
            ]);

        $segments = $audience->segments()->withCount('subscribers')->latest()->get()->map(fn (Segment $segment) => [
            'uuid' => $segment->uuid,
            'name' => $segment->name,
            'description' => $segment->description,
            'match_type' => $segment->match_type->value,
            'rules' => $segment->rules,
            'subscribers_count' => $segment->subscribers_count,
        ]);

        $forms = $audience->subscribeForms()->withCount('subscribers')->latest()->get()->map(fn (SubscribeForm $form) => [
            'uuid' => $form->uuid,
            'name' => $form->name,
            'headline' => $form->headline,
            'published' => $form->isPublished(),
            'subscribers_count' => $form->subscribers_count,
            'public_url' => route('public.subscribe_forms.show', $form),
        ]);

        $tags = $currentTeam->tags()->orderBy('name')->get()->map(fn (Tag $tag) => [
            'uuid' => $tag->uuid,
            'name' => $tag->name,
            'color' => $tag->color,
        ]);

        return Inertia::render('audiences/show', [
            'audience' => $this->audiencePayload($audience),
            'subscribers' => $subscribers,
            'companies' => $currentTeam->companies()->orderBy('name')->get(['uuid', 'name']),
            'tags' => $tags,
            'subscriberStats' => $buildSubscriberStats->handle($audience),
            'segments' => $segments,
            'forms' => $forms,
            'contactImports' => $audience->contactImports()
                ->latest()
                ->limit(5)
                ->get()
                ->map->toInertia()
                ->values(),
            'filters' => $filters,
            'canManage' => Gate::allows('update', $audience),
        ]);
    }

    public function update(SaveAudienceRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        Gate::authorize('update', $audience);
        $audience->update($this->audienceAttributes($request, $currentTeam));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Audience updated.')]);

        return back();
    }

    public function destroy(DeleteAudienceRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        $team = $audience->team;
        $audience->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Audience deleted.')]);

        return to_route('audiences.index', ['current_team' => $team]);
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

    /**
     * @return array{search: string, status: string, source: string}
     */
    private function subscriberFilters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => SubscriberStatus::tryFrom($status)->value ?? 'all',
            'source' => SubscriberSource::tryFrom($source)->value ?? 'all',
        ];
    }

    /**
     * @return array{search: string, filter: string, sort: string}
     */
    private function indexFilters(Request $request): array
    {
        $filter = $request->string('filter')->toString();
        $sort = $request->string('sort')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'filter' => in_array($filter, ['active', 'empty', 'segments', 'forms'], true)
                ? $filter
                : 'all',
            'sort' => in_array($sort, ['oldest', 'subscribers'], true)
                ? $sort
                : 'newest',
        ];
    }

    private function currentTeam(Request $request): Team
    {
        $team = $request->route('current_team');

        abort_unless($team instanceof Team, 404);

        return $team;
    }

    /** @return array<string, mixed> */
    private function audienceAttributes(
        SaveAudienceRequest $request,
        Team $team,
    ): array {
        $attributes = $request->validated();

        if (array_key_exists('sender_uuid', $attributes)) {
            $senderUuid = $attributes['sender_uuid'];
            unset($attributes['sender_uuid']);

            if ($senderUuid === SaveAudienceRequest::WORKSPACE_DEFAULT_SENDER) {
                $attributes['from_name'] = null;
                $attributes['from_address'] = null;
                $attributes['reply_to'] = null;
            } else {
                $sender = $team->verifiedSenders()
                    ->where('uuid', $senderUuid)
                    ->firstOrFail();

                $attributes['from_name'] = $sender->name;
                $attributes['from_address'] = $sender->email;
                $attributes['reply_to'] = $sender->reply_to;
            }
        }

        if (array_key_exists('double_opt_in_email_uuid', $attributes)) {
            $attributes['double_opt_in_email_id'] = $attributes['double_opt_in_email_uuid'] === null
                ? null
                : $team->transactionalEmails()
                    ->where('uuid', $attributes['double_opt_in_email_uuid'])
                    ->where('status', TransactionalEmailStatus::Published->value)
                    ->value('id');

            unset($attributes['double_opt_in_email_uuid']);
        }

        if (array_key_exists('double_opt_in', $attributes) && ! $attributes['double_opt_in']) {
            $attributes['double_opt_in_email_id'] = null;
        }

        return $attributes;
    }
}
