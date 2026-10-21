<?php

namespace App\Http\Controllers;

use App\Concerns\ReadsQueryFilters;
use App\Concerns\SearchesText;
use App\Enums\ContactCompanyAssignmentMode;
use App\Http\Requests\SaveCompanyRequest;
use App\Jobs\RelinkCompanyContacts;
use App\Jobs\ResolveCompanyFavicon;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use ReadsQueryFilters;
    use SearchesText;

    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Company::class, $currentTeam]);
        $filters = $this->indexFilters($request);

        $companies = $currentTeam->companies()
            ->with('domains:id,company_id,domain')
            ->withCount('contacts')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(fn ($companyQuery) => $this->whereAnyColumnContains($companyQuery, ['name'], $filters['search'])
                    ->orWhereHas('domains', fn ($domainQuery) => $this->whereAnyColumnContains($domainQuery, ['domain'], $filters['search'])));
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

    public function store(SaveCompanyRequest $request, Team $currentTeam): RedirectResponse
    {
        Gate::authorize('create', [Company::class, $currentTeam]);
        $company = $this->saveAtomically($request, function () use ($request, $currentTeam): Company {
            $company = $currentTeam->companies()->create(Arr::only($request->validated(), ['name']));
            $this->syncDomains($company, $currentTeam, $request->validated('domains'));

            return $company;
        });

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
                        $this->whereAnyColumnContains($query, ['email', 'first_name', 'last_name'], $filters['search']);
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
            'canManageContacts' => Gate::allows('create', [Contact::class, $currentTeam]),
        ]);
    }

    public function update(SaveCompanyRequest $request, Team $currentTeam, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);
        $this->saveAtomically($request, function () use ($request, $currentTeam, $company): Company {
            $company->update(Arr::only($request->validated(), ['name']));
            $this->syncDomains($company, $currentTeam, $request->validated('domains'));

            return $company;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        DB::transaction(function () use ($currentTeam, $company): void {
            // Automatic contacts are on this company's domains, which no other company
            // owns, so they simply lose the company. Manually assigned contacts turn
            // automatic and may match another company by their own email domain.
            $manualContactDomains = array_values($company->contacts()
                ->where('company_assignment_mode', ContactCompanyAssignmentMode::Manual)
                ->pluck('email')
                ->map(fn (string $email): string => Str::afterLast($email, '@'))
                ->unique()
                ->all());

            $company->contacts()->update([
                'company_id' => null,
                'company_assignment_mode' => ContactCompanyAssignmentMode::Automatic,
            ]);
            $company->delete();

            if ($manualContactDomains !== []) {
                RelinkCompanyContacts::dispatch($currentTeam->id, $manualContactDomains)->afterCommit();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index', $currentTeam);
    }

    /** @return array{uuid: string, name: string, favicon: string, domains: list<string>, contacts_count: int, created_at: string|null} */
    private function payload(Company $company): array
    {
        return [
            'uuid' => $company->uuid,
            'name' => $company->name,
            'favicon' => $company->faviconOrFallbackUrl(),
            'domains' => array_values($company->domains->sortBy('id')->pluck('domain')->all()),
            'contacts_count' => $company->contacts_count ?? $company->contacts()->count(),
            'created_at' => $company->created_at?->toISOString(),
        ];
    }

    /** @return array{search: string, sort: string} */
    private function indexFilters(Request $request): array
    {
        $sort = $this->queryFilter($request, 'sort');

        return [
            'search' => $this->queryFilter($request, 'search'),
            'sort' => in_array($sort, ['oldest', 'name', 'contacts'], true) ? $sort : 'newest',
        ];
    }

    /** @return array{search: string, audience: string} */
    private function showFilters(Request $request): array
    {
        $audience = $this->queryFilter($request, 'audience');

        return [
            'search' => $this->queryFilter($request, 'search'),
            'audience' => Str::isUuid($audience) ? $audience : '',
        ];
    }

    /**
     * Save a company in one transaction. Validation checks names and domains without
     * locking, so a concurrent request can still claim one first; report that as the
     * same validation error instead of a server error and a half-saved company.
     *
     * @param  Closure(): Company  $save
     */
    private function saveAtomically(SaveCompanyRequest $request, Closure $save): Company
    {
        try {
            return DB::transaction($save);
        } catch (UniqueConstraintViolationException) {
            // The winning row may itself have been rolled back by now; still answer
            // with a validation error rather than a server error.
            throw ValidationException::withMessages($request->conflictErrors() ?: [
                'domains' => __('Someone else changed these companies at the same time. Try again.'),
            ]);
        }
    }

    /**
     * Store the submitted domains, then queue a contact re-link for only the domains
     * that were added or removed, and a favicon lookup if the favicon domain changed.
     *
     * @param  list<string>  $domains
     */
    private function syncDomains(Company $company, Team $team, array $domains): void
    {
        $previousDomains = $company->wasRecentlyCreated ? [] : $company->domains()->pluck('domain')->all();
        $previousFaviconDomain = $company->wasRecentlyCreated ? null : $company->faviconDomain();

        $company->domains()->whereNotIn('domain', $domains)->delete();

        foreach ($domains as $domain) {
            $company->domains()->firstOrCreate([
                'team_id' => $team->id,
                'domain' => $domain,
            ]);
        }

        $changedDomains = array_values(array_unique([
            ...array_diff($domains, $previousDomains),
            ...array_diff($previousDomains, $domains),
        ]));
        sort($changedDomains);

        if ($changedDomains !== []) {
            RelinkCompanyContacts::dispatch($team->id, $changedDomains)->afterCommit();
        }

        $this->refreshFavicon($company, $previousFaviconDomain);
    }

    private function refreshFavicon(Company $company, ?string $previousFaviconDomain): void
    {
        $faviconDomain = $company->faviconDomain();

        if ($faviconDomain === null) {
            return;
        }

        if ($faviconDomain !== $previousFaviconDomain) {
            $company->forceFill(['favicon' => null])->save();
        } elseif ($company->favicon !== null) {
            return;
        }

        ResolveCompanyFavicon::dispatch($company->id, $faviconDomain)->afterCommit();
    }
}
