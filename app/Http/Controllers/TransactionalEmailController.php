<?php

namespace App\Http\Controllers;

use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\EmailEditor;
use App\Enums\TransactionalEmailStatus;
use App\Http\Requests\SendTestTransactionalEmailRequest;
use App\Http\Requests\StoreTransactionalEmailRequest;
use App\Http\Requests\UpdateTransactionalEmailRequest;
use App\Jobs\SendTransactionalEmailTest;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionalEmailController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [TransactionalEmail::class, $currentTeam]);

        $filters = $this->indexFilters($request);

        $emails = $currentTeam->transactionalEmails()
            ->select([
                'id',
                'uuid',
                'name',
                'slug',
                'subject',
                'editor',
                'status',
                'last_tested_at',
                'published_at',
                'updated_at',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->whereAny(['name', 'slug', 'subject'], 'like', $search);
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['editor'] !== '', fn ($query) => $query->where('editor', $filters['editor']))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TransactionalEmail $email): array => [
                'uuid' => $email->uuid,
                'name' => $email->name,
                'slug' => $email->slug,
                'subject' => $email->subject,
                'editor' => $email->editor->value,
                'status' => $email->status->value,
                'last_tested_at' => $email->last_tested_at?->toISOString(),
                'updated_at' => $email->updated_at?->toISOString(),
            ]);

        return Inertia::render('transactional/index', [
            'emails' => $emails,
            'templates' => $this->templatesFor($currentTeam),
            'filters' => $filters,
            'defaultEditor' => $currentTeam->email_editor->value,
            'canManage' => Gate::allows('create', [TransactionalEmail::class, $currentTeam]),
        ]);
    }

    public function store(StoreTransactionalEmailRequest $request, Team $currentTeam): RedirectResponse
    {
        $template = $request->filled('template')
            ? EmailTemplate::query()
                ->availableTo($currentTeam)
                ->where('uuid', $request->string('template'))
                ->first()
            : null;

        $editor = $currentTeam->email_editor;
        $name = $request->string('name')->value();

        $body = $template
            ? ['html' => $template->html, 'source' => $template->source, 'design' => $template->design]
            : EmailTemplate::blankBodyFor($editor);

        $email = $currentTeam->transactionalEmails()->create([
            'name' => $name,
            'slug' => TransactionalEmail::uniqueSlugFor($currentTeam, $name),
            'subject' => filled($template?->subject) ? $template->subject : $name,
            'preheader' => $template?->preheader,
            'editor' => $editor,
            'html' => $body['html'],
            'source' => $body['source'],
            'design' => $body['design'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email created.')]);

        return to_route('transactional_emails.edit', ['current_team' => $currentTeam, 'transactionalEmail' => $email]);
    }

    public function edit(Team $currentTeam, TransactionalEmail $transactionalEmail): Response
    {
        Gate::authorize('view', $transactionalEmail);

        return Inertia::render('transactional/edit', [
            'email' => [
                'uuid' => $transactionalEmail->uuid,
                'name' => $transactionalEmail->name,
                'slug' => $transactionalEmail->slug,
                'description' => $transactionalEmail->description,
                'subject' => $transactionalEmail->subject,
                'preheader' => $transactionalEmail->preheader,
                'from_name' => $transactionalEmail->from_name,
                'from_address' => $transactionalEmail->from_address,
                'reply_to' => $transactionalEmail->reply_to,
                'editor' => $currentTeam->email_editor->value,
                'status' => $transactionalEmail->status->value,
                'html' => $transactionalEmail->html ?? '',
                'source' => $transactionalEmail->source ?? ($currentTeam->email_editor->usesSource() ? ($transactionalEmail->html ?? '') : ''),
                'design' => $transactionalEmail->design,
                'variables' => $transactionalEmail->variables,
                'slug_frozen' => $transactionalEmail->slugIsFrozen(),
                'last_tested_at' => $transactionalEmail->last_tested_at?->toISOString(),
                'updated_at' => $transactionalEmail->updated_at?->toISOString(),
            ],
            'defaults' => [
                'from_name' => $currentTeam->email_from_name ?? config('mail.from.name'),
                'from_address' => $currentTeam->email_from_address ?? config('mail.from.address'),
                'reply_to' => $currentTeam->email_reply_to,
            ],
            'canManage' => Gate::allows('update', $transactionalEmail),
        ]);
    }

    public function update(
        UpdateTransactionalEmailRequest $request,
        Team $currentTeam,
        TransactionalEmail $transactionalEmail,
        RenderTransactionalContent $renderer,
    ): RedirectResponse {
        $editor = $currentTeam->email_editor;
        $detected = $renderer->detect(
            $request->string('subject')->value(),
            (string) $request->input('preheader', ''),
            $request->string('html')->value(),
        );

        /** @var list<array{key?: mixed, example?: mixed}> $declared */
        $declared = $request->input('variables', []);

        $transactionalEmail->update([
            ...$request->safe()->only([
                'name',
                'slug',
                'description',
                'subject',
                'preheader',
                'from_name',
                'from_address',
                'reply_to',
                'html',
                'source',
            ]),
            'editor' => $editor,
            'source' => $editor->usesSource() ? $request->input('source') : null,
            'design' => $editor === EmailEditor::Builder ? $request->input('design') : null,
            'variables' => $renderer->merge($declared, $detected),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email saved.')]);

        return back();
    }

    public function sendTest(
        SendTestTransactionalEmailRequest $request,
        Team $currentTeam,
        TransactionalEmail $transactionalEmail,
        RenderTransactionalContent $renderer,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->input('data', []);

        SendTransactionalEmailTest::dispatch(
            $transactionalEmail->id,
            $request->string('to')->value(),
            $renderer->text($transactionalEmail->subject, $data),
            $renderer->html($transactionalEmail->html ?? '', $data),
        )->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Test email queued for :address.', ['address' => $request->string('to')->value()]),
        ]);

        return back();
    }

    public function publish(Team $currentTeam, TransactionalEmail $transactionalEmail): RedirectResponse
    {
        Gate::authorize('publish', $transactionalEmail);

        if (blank($transactionalEmail->subject) || blank($transactionalEmail->html) || blank($transactionalEmail->slug)) {
            throw ValidationException::withMessages([
                'email' => __('Add a subject, content, and identifier before publishing.'),
            ]);
        }

        $transactionalEmail->update([
            'status' => TransactionalEmailStatus::Published,
            'published_at' => $transactionalEmail->published_at ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email published.')]);

        return back();
    }

    public function unpublish(Team $currentTeam, TransactionalEmail $transactionalEmail): RedirectResponse
    {
        Gate::authorize('publish', $transactionalEmail);

        $transactionalEmail->update([
            'status' => TransactionalEmailStatus::Draft,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email unpublished.')]);

        return back();
    }

    public function duplicate(Team $currentTeam, TransactionalEmail $transactionalEmail): RedirectResponse
    {
        Gate::authorize('create', [TransactionalEmail::class, $currentTeam]);

        $name = TransactionalEmail::uniqueCopyName($currentTeam, $transactionalEmail->name);

        $copy = $currentTeam->transactionalEmails()->create([
            'name' => $name,
            'slug' => TransactionalEmail::uniqueSlugFor($currentTeam, $name),
            'description' => $transactionalEmail->description,
            'subject' => $transactionalEmail->subject,
            'preheader' => $transactionalEmail->preheader,
            'from_name' => $transactionalEmail->from_name,
            'from_address' => $transactionalEmail->from_address,
            'reply_to' => $transactionalEmail->reply_to,
            'editor' => $currentTeam->email_editor,
            'html' => $transactionalEmail->html,
            'source' => $transactionalEmail->source,
            'design' => $transactionalEmail->design,
            'variables' => $transactionalEmail->variables,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email duplicated.')]);

        return to_route('transactional_emails.edit', ['current_team' => $currentTeam, 'transactionalEmail' => $copy]);
    }

    public function destroy(Team $currentTeam, TransactionalEmail $transactionalEmail): RedirectResponse
    {
        Gate::authorize('delete', $transactionalEmail);
        $transactionalEmail->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transactional email deleted.')]);

        return to_route('transactional_emails.index', ['current_team' => $currentTeam]);
    }

    /**
     * @return array{q: string, status: string, editor: string}
     */
    private function indexFilters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $editor = $request->string('editor')->toString();

        return [
            'q' => $request->string('q')->trim()->toString(),
            'status' => TransactionalEmailStatus::tryFrom($status)->value ?? '',
            'editor' => EmailEditor::tryFrom($editor)->value ?? '',
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
}
