<?php

namespace App\Http\Controllers;

use App\Enums\ContactCompanyAssignmentMode;
use App\Http\Requests\SaveCompanyRequest;
use App\Jobs\ResolveCompanyFavicon;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Services\ResolveContactCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Company::class, $currentTeam]);
        $filters = $this->indexFilters($request);

        $companies = $currentTeam->companies()
            ->with('domains:id,company_id,domain')
            ->withCount('contacts')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.mb_strtolower($filters['search']).'%';
                $query->where(fn ($companyQuery) => $companyQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereHas('domains', fn ($domainQuery) => $domainQuery->where('domain', 'like', $search)));
            });

        $companies = match ($filters['sort']) {
            'oldest' => $companies->oldest('created_at')->orderBy('id'),
            'name' => $companies->orderBy('normalized_name')->orderBy('id'),
            'contacts' => $companies->orderByDesc('contacts_count')->latest(),
            default => $companies->latest()->orderByDesc('id'),
        };

        return Inertia::render('companies/index', [
            'companies' => $companies->paginate(20)->withQueryString()->through(fn (Company $company): array => $this->payload($company)),
            'filters' => $filters,
            'canManage' => Gate::allows('create', [Company::class, $currentTeam]),
        ]);
    }

    public function store(SaveCompanyRequest $request, Team $currentTeam, ResolveContactCompany $resolveCompany): RedirectResponse
    {
        Gate::authorize('create', [Company::class, $currentTeam]);
        $company = $currentTeam->companies()->create(Arr::only($request->validated(), ['name']));
        $this->syncDomains($company, $currentTeam, $request->validated('domains'));
        $resolveCompany->refreshTeam($currentTeam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', [$currentTeam, $company]);
    }

    public function show(Request $request, Team $currentTeam, Company $company): Response
    {
        Gate::authorize('view', $company);
        $filters = $this->showFilters($request);

        return Inertia::render('companies/show', [
            'company' => [
                ...$this->payload($company->load('domains')),
                'contacts' => $company->contacts()
                    ->with([
                        'tags:id,uuid,name,color',
                        'subscribers.audience:id,uuid,name',
                    ])
                    ->withCount('subscribers')
                    ->when($filters['search'] !== '', function ($query) use ($filters): void {
                        $term = '%'.mb_strtolower($filters['search']).'%';
                        $query->where(fn ($contactQuery) => $contactQuery
                            ->whereRaw('LOWER(email) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]));
                    })
                    ->when($filters['audience'] !== '', fn ($query) => $query->whereHas(
                        'subscribers.audience',
                        fn ($audienceQuery) => $audienceQuery->where('uuid', $filters['audience']),
                    ))
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Contact $contact): array => [
                        'uuid' => $contact->uuid,
                        'email' => $contact->email,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'company_assignment_mode' => $contact->company_assignment_mode->value,
                        'avatar' => $contact->avatar,
                        'company' => [
                            'uuid' => $company->uuid,
                            'name' => $company->name,
                        ],
                        'tags' => array_values($contact->tags->map(fn (Tag $tag): array => [
                            'uuid' => $tag->uuid,
                            'name' => $tag->name,
                            'color' => $tag->color,
                        ])->all()),
                        'audiences_count' => $contact->subscribers_count,
                        'audiences' => $contact->subscribers
                            ->map(fn (Subscriber $subscriber): array => [
                                'uuid' => $subscriber->audience->uuid,
                                'name' => $subscriber->audience->name,
                            ])
                            ->values()
                            ->all(),
                        'created_at' => $contact->created_at?->toISOString(),
                    ]),
            ],
            'companies' => $currentTeam->companies()->orderBy('name')->get(['uuid', 'name']),
            'audiences' => $currentTeam->audiences()->orderBy('name')->get(['uuid', 'name']),
            'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
            'filters' => $filters,
            'canManage' => Gate::allows('update', $company),
        ]);
    }

    public function update(SaveCompanyRequest $request, Team $currentTeam, Company $company, ResolveContactCompany $resolveCompany): RedirectResponse
    {
        Gate::authorize('update', $company);
        $company->update(Arr::only($request->validated(), ['name']));
        $this->syncDomains($company, $currentTeam, $request->validated('domains'));
        $resolveCompany->refreshTeam($currentTeam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Company $company, ResolveContactCompany $resolveCompany): RedirectResponse
    {
        Gate::authorize('delete', $company);
        $company->contacts()->update([
            'company_id' => null,
            'company_assignment_mode' => ContactCompanyAssignmentMode::Automatic,
        ]);
        $company->delete();
        $resolveCompany->refreshTeam($currentTeam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index', $currentTeam);
    }

    /** @return array{uuid: string, name: string, favicon: string|null, domains: list<string>, contacts_count: int, created_at: string|null} */
    private function payload(Company $company): array
    {
        return [
            'uuid' => $company->uuid,
            'name' => $company->name,
            'favicon' => $company->favicon,
            'domains' => array_values($company->domains->pluck('domain')->all()),
            'contacts_count' => $company->contacts_count ?? $company->contacts()->count(),
            'created_at' => $company->created_at?->toISOString(),
        ];
    }

    /** @return array{search: string, sort: string} */
    private function indexFilters(Request $request): array
    {
        $sort = $request->string('sort')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => in_array($sort, ['oldest', 'name', 'contacts'], true) ? $sort : 'newest',
        ];
    }

    /** @return array{search: string, audience: string} */
    private function showFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'audience' => $request->string('audience')->toString(),
        ];
    }

    /** @param list<string> $domains */
    private function syncDomains(Company $company, Team $team, array $domains): void
    {
        $company->domains()->whereNotIn('domain', $domains)->delete();

        foreach ($domains as $domain) {
            $company->domains()->firstOrCreate([
                'team_id' => $team->id,
                'domain' => $domain,
            ]);
        }

        $company->favicon = $company->fallbackFaviconUrl();
        $company->save();

        ResolveCompanyFavicon::dispatch(
            $company->id,
            $domains[0],
            $company->updated_at?->toISOString() ?? '',
        );
    }
}
