<?php

namespace App\Http\Controllers;

use App\Actions\Emails\BuildCampaignSeriesReport;
use App\Enums\CampaignSeriesGoal;
use App\Enums\EmailStatus;
use App\Http\Requests\StoreCampaignSeriesRequest;
use App\Http\Requests\UpdateCampaignSeriesRequest;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CampaignSeriesController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [CampaignSeries::class, $currentTeam]);

        $search = $request->string('q')->trim()->toString();
        $series = $currentTeam->campaignSeries()
            ->select(['id', 'uuid', 'name', 'description', 'goal', 'objective', 'primary_cta_url', 'updated_at'])
            ->withCount([
                'emails',
                'emails as sent_campaigns_count' => fn ($query) => $query
                    ->where(fn ($status) => $status
                        ->whereNot('status', EmailStatus::Draft)
                        ->orWhereNotNull('sent_at')),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->whereAny(['name', 'description', 'objective'], 'like', $term);
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through($this->seriesIndexPayload(...));

        return Inertia::render('campaign-series/index', [
            'series' => $series,
            'filters' => ['q' => $search],
            'goals' => CampaignSeriesGoal::options(),
            'canManage' => Gate::allows('create', [CampaignSeries::class, $currentTeam]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesIndexPayload(CampaignSeries $campaignSeries): array
    {
        return [
            'uuid' => $campaignSeries->uuid,
            'name' => $campaignSeries->name,
            'description' => $campaignSeries->description,
            'goal' => $campaignSeries->goal->value,
            'goal_label' => $campaignSeries->goal->label(),
            'objective' => $campaignSeries->objective,
            'primary_cta_url' => $campaignSeries->primary_cta_url,
            'campaigns_count' => $campaignSeries->emails_count,
            'sent_campaigns_count' => $campaignSeries->sent_campaigns_count,
            'updated_at' => $campaignSeries->updated_at?->toISOString(),
        ];
    }

    public function store(StoreCampaignSeriesRequest $request, Team $currentTeam): RedirectResponse
    {
        $campaignSeries = $currentTeam->campaignSeries()->create($this->attributes($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign series created.')]);

        return to_route('campaign_series.show', [$currentTeam, $campaignSeries]);
    }

    public function show(
        Team $currentTeam,
        CampaignSeries $campaignSeries,
        BuildCampaignSeriesReport $report,
    ): Response {
        Gate::authorize('view', $campaignSeries);

        return Inertia::render('campaign-series/show', [
            'series' => [
                'uuid' => $campaignSeries->uuid,
                'name' => $campaignSeries->name,
                'description' => $campaignSeries->description,
                'goal' => $campaignSeries->goal->value,
                'goal_label' => $campaignSeries->goal->label(),
                'objective' => $campaignSeries->objective,
                'primary_cta_url' => $campaignSeries->primary_cta_url,
                'updated_at' => $campaignSeries->updated_at?->toISOString(),
            ],
            'report' => $report->handle($campaignSeries),
            'availableCampaigns' => $currentTeam->emails()
                ->whereNull('campaign_series_id')
                ->select(['uuid', 'name', 'subject', 'status', 'sent_at', 'updated_at'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Email $email): array => [
                    'uuid' => $email->uuid,
                    'name' => $email->name,
                    'subject' => $email->subject,
                    'status' => ($email->status === EmailStatus::Draft && $email->sent_at)
                        ? EmailStatus::Sent->value
                        : $email->status->value,
                ])
                ->values(),
            'templates' => $this->templatesFor($currentTeam),
            'defaultEditor' => $currentTeam->email_editor->value,
            'goals' => CampaignSeriesGoal::options(),
            'canManage' => Gate::allows('update', $campaignSeries),
        ]);
    }

    public function update(
        UpdateCampaignSeriesRequest $request,
        Team $currentTeam,
        CampaignSeries $campaignSeries,
    ): RedirectResponse {
        $campaignSeries->update($this->attributes($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign series saved.')]);

        return back();
    }

    public function destroy(Team $currentTeam, CampaignSeries $campaignSeries): RedirectResponse
    {
        Gate::authorize('delete', $campaignSeries);
        $campaignSeries->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campaign series deleted. Its campaigns were kept.'),
        ]);

        return to_route('campaign_series.index', $currentTeam);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        foreach (['description', 'objective', 'primary_cta_url'] as $key) {
            $value = Str::of((string) ($validated[$key] ?? ''))->trim()->toString();
            $validated[$key] = $value !== '' ? $value : null;
        }

        return $validated;
    }

    /**
     * @return list<array{uuid: string, name: string, description: string|null, editor: string, is_starter: bool}>
     */
    private function templatesFor(Team $team): array
    {
        return array_values(EmailTemplate::query()
            ->availableTo($team)
            ->where('editor', $team->email_editor->value)
            ->orderedForPicker()
            ->get()
            ->map(fn (EmailTemplate $template): array => [
                'uuid' => $template->uuid,
                'name' => $template->name,
                'description' => $template->description,
                'editor' => $template->editor->value,
                'is_starter' => $template->isStarter(),
            ])
            ->all());
    }
}
