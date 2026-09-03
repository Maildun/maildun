<?php

namespace App\Http\Controllers;

use App\Enums\EmailEditor;
use App\Http\Requests\SaveEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [EmailTemplate::class, $currentTeam]);

        $filters = $this->indexFilters($request);

        $templates = EmailTemplate::query()
            ->availableTo($currentTeam)
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->whereAny(['name', 'description', 'subject'], 'like', $search);
            })
            ->when($filters['editor'] !== '', fn ($query) => $query->where('editor', $filters['editor']))
            ->when($filters['type'] === 'starter', fn ($query) => $query->whereNull('team_id'))
            ->when($filters['type'] === 'team', fn ($query) => $query->where('team_id', $currentTeam->id))
            ->orderedForPicker()
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'uuid' => $template->uuid,
                'name' => $template->name,
                'description' => $template->description,
                'editor' => $template->editor->value,
                'subject' => $template->subject,
                'preheader' => $template->preheader,
                'html' => $template->html,
                'source' => $template->source,
                'design' => $template->design,
                'is_starter' => $template->isStarter(),
                'updated_at' => $template->updated_at?->toISOString(),
            ]);

        return Inertia::render('email-templates/index', [
            'templates' => $templates,
            'filters' => $filters,
            'defaultEditor' => $currentTeam->email_editor->value,
            'canManage' => Gate::allows('create', [EmailTemplate::class, $currentTeam]),
        ]);
    }

    public function store(SaveEmailTemplateRequest $request, Team $currentTeam): RedirectResponse
    {
        $template = $currentTeam->emailTemplates()->create($this->attributesFrom($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        if ($request->boolean('compose')) {
            return to_route('email_templates.edit', [$currentTeam, $template]);
        }

        return back();
    }

    public function edit(Team $currentTeam, EmailTemplate $emailTemplate): Response
    {
        Gate::authorize('update', $emailTemplate);

        return Inertia::render('email-templates/edit', [
            'template' => [
                'uuid' => $emailTemplate->uuid,
                'name' => $emailTemplate->name,
                'description' => $emailTemplate->description,
                'subject' => $emailTemplate->subject,
                'preheader' => $emailTemplate->preheader,
                'editor' => $emailTemplate->editor->value,
                'html' => $emailTemplate->html ?? '',
                'source' => $emailTemplate->source ?? ($emailTemplate->editor->usesSource() ? ($emailTemplate->html ?? '') : ''),
                'design' => $emailTemplate->design,
                'is_starter' => $emailTemplate->isStarter(),
                'updated_at' => $emailTemplate->updated_at?->toISOString(),
            ],
            'templates' => $this->templatesFor($currentTeam),
            'defaultEditor' => $currentTeam->email_editor->value,
            'canManage' => Gate::allows('update', $emailTemplate),
        ]);
    }

    public function update(SaveEmailTemplateRequest $request, Team $currentTeam, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($this->attributesFrom($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return back();
    }

    /**
     * Copy a template — including a starter — into the team's own library so it
     * can be edited.
     */
    public function duplicate(Team $currentTeam, EmailTemplate $emailTemplate): RedirectResponse
    {
        Gate::authorize('create', [EmailTemplate::class, $currentTeam]);

        abort_unless(
            $emailTemplate->isStarter() || $emailTemplate->team_id === $currentTeam->id,
            404,
        );

        $copy = $currentTeam->emailTemplates()->create([
            'name' => $this->uniqueCopyName($currentTeam, $emailTemplate->name),
            'description' => $emailTemplate->description,
            'subject' => $emailTemplate->subject,
            'preheader' => $emailTemplate->preheader,
            'editor' => $emailTemplate->editor,
            'html' => $emailTemplate->html,
            'source' => $emailTemplate->source,
            'design' => $emailTemplate->design,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template duplicated.')]);

        return to_route('email_templates.edit', [$currentTeam, $copy]);
    }

    public function destroy(Team $currentTeam, EmailTemplate $emailTemplate): RedirectResponse
    {
        Gate::authorize('delete', $emailTemplate);
        $emailTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return to_route('email_templates.index', ['current_team' => $currentTeam]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributesFrom(SaveEmailTemplateRequest $request): array
    {
        $template = $request->route('emailTemplate');
        $editor = $request->editor();
        $savingBody = $request->filled('html') || $request->filled('source') || $request->filled('design');

        if ($savingBody) {
            $html = $request->input('html');
            $source = $editor->usesSource() ? $request->input('source') : null;
            $design = $editor === EmailEditor::Builder ? $request->input('design') : null;
        } else {
            $body = EmailTemplate::blankBodyFor($editor);
            $html = $body['html'];
            $source = $body['source'];
            $design = $body['design'];
        }

        $subject = $request->input('subject');

        if (! $template instanceof EmailTemplate && blank($subject)) {
            $subject = $request->string('name')->value();
        }

        return [
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'subject' => blank($subject) ? null : $subject,
            'preheader' => blank($request->input('preheader')) ? null : $request->input('preheader'),
            'editor' => $editor,
            'html' => $html,
            'source' => $source,
            'design' => $design,
        ];
    }

    /**
     * @return array{q: string, editor: string, type: string}
     */
    private function indexFilters(Request $request): array
    {
        $editor = $request->string('editor')->toString();
        $type = $request->string('type')->toString();

        return [
            'q' => $request->string('q')->trim()->toString(),
            'editor' => EmailEditor::tryFrom($editor)->value ?? '',
            'type' => in_array($type, ['starter', 'team'], true) ? $type : '',
        ];
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
     * Template names are unique per team, so a copy needs a free name.
     */
    protected function uniqueCopyName(Team $team, string $name): string
    {
        $candidate = mb_substr("{$name} copy", 0, 255);
        $suffix = 2;

        while ($team->emailTemplates()->where('name', $candidate)->exists()) {
            $candidate = mb_substr("{$name} copy {$suffix}", 0, 255);
            $suffix++;
        }

        return $candidate;
    }
}
