<?php

namespace App\Http\Controllers;

use App\Enums\ContactImportIssue;
use App\Enums\ContactImportStatus;
use App\Enums\StorageBackend;
use App\Http\Requests\StartContactImportRequest;
use App\Http\Requests\StoreContactImportRequest;
use App\Jobs\ProcessContactImport;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Subscriber;
use App\Models\Team;
use App\Services\ContactImportCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [ContactImport::class, $currentTeam]);

        return Inertia::render('contacts/imports/index', [
            'imports' => $currentTeam->contactImports()
                ->with(['audience:id,uuid,name,team_id', 'uploadedBy:id,name'])
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (ContactImport $contactImport): array => $contactImport->toInertia()),
            'canImport' => $this->importableAudiences($currentTeam)->isNotEmpty()
                || Gate::allows('create', [Contact::class, $currentTeam]),
        ]);
    }

    public function create(Request $request, Team $currentTeam): Response
    {
        $audiences = $this->importableAudiences($currentTeam);
        $canImportContacts = Gate::allows('create', [Contact::class, $currentTeam]);

        abort_unless($canImportContacts || $audiences->isNotEmpty(), 403);

        $audience = $audiences->firstWhere('uuid', $request->string('audience')->toString());

        return Inertia::render('contacts/imports/create', [
            'audiences' => $audiences->map(fn (Audience $audience): array => [
                'uuid' => $audience->uuid,
                'name' => $audience->name,
            ])->values(),
            'selectedAudience' => $audience->uuid ?? ($canImportContacts ? null : $audiences->first()?->uuid),
            'canImportContacts' => $canImportContacts,
        ]);
    }

    public function store(StoreContactImportRequest $request, Team $currentTeam, ContactImportCsv $csv): RedirectResponse
    {
        $file = $request->file('file');
        $audience = $request->targetAudience();

        try {
            $analysis = $csv->analyze((string) $file->getRealPath());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($analysis['total_rows'] === 0) {
            throw ValidationException::withMessages(['file' => __('The CSV has a header row but no contacts.')]);
        }

        $disk = StorageBackend::current()->privateDisk();
        $path = $file->store('contact-imports/'.$currentTeam->uuid, $disk);
        $attributeKeys = $audience?->audienceAttributes()->get(['key'])
            ->map(fn (AudienceAttribute $attribute): string => $attribute->key)
            ->values()
            ->all() ?? [];

        $contactImport = ContactImport::query()->create([
            'team_id' => $currentTeam->id,
            'audience_id' => $audience?->id,
            'uploaded_by' => $request->user()?->id,
            'consent_ip' => $request->ip(),
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'delimiter' => $analysis['delimiter'],
            'headers' => $analysis['headers'],
            'sample_rows' => $analysis['sample_rows'],
            'column_map' => $csv->suggestMapping($analysis['headers'], $attributeKeys),
            'total_rows' => $analysis['total_rows'],
            'status' => ContactImportStatus::Draft,
        ]);

        return to_route('contacts.imports.show', [$currentTeam, $contactImport]);
    }

    public function show(Team $currentTeam, ContactImport $contactImport, ContactImportCsv $csv): Response
    {
        Gate::authorize('view', $contactImport);
        $contactImport->loadMissing(['audience:id,uuid,name,team_id', 'uploadedBy:id,name']);
        $isDraft = $contactImport->status === ContactImportStatus::Draft;

        return Inertia::render('contacts/imports/show', [
            'contactImport' => fn (): array => $contactImport->fresh(['audience:id,uuid,name,team_id', 'uploadedBy:id,name'])?->toInertia() ?? $contactImport->toInertia(),
            'rowErrors' => fn (): array => array_slice($contactImport->fresh()?->rowErrors() ?? [], 0, 50),
            'reviewFlags' => fn (): array => array_slice($contactImport->fresh()->review_flags ?? [], 0, 100),
            'issues' => collect(ContactImportIssue::cases())->map(fn (ContactImportIssue $issue): array => [
                'value' => $issue->value,
                'label' => $issue->label(),
                'actions' => $issue->actions(),
            ])->values(),
            'mapping' => $isDraft ? [
                'headers' => $contactImport->headers ?? [],
                'sampleRows' => $contactImport->sample_rows ?? [],
                'columnMap' => $contactImport->column_map ?? [],
                'fields' => $csv->fieldOptions($contactImport->audience),
                'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
            ] : null,
            'canRetry' => $contactImport->status === ContactImportStatus::Failed
                && Storage::disk($contactImport->disk)->exists($contactImport->path),
            'canManage' => Gate::allows('update', $contactImport),
        ]);
    }

    public function start(StartContactImportRequest $request, Team $currentTeam, ContactImport $contactImport): RedirectResponse
    {
        abort_unless($contactImport->status === ContactImportStatus::Draft, 409);

        $contactImport->update([
            'column_map' => array_map(
                fn (mixed $field): ?string => is_string($field) && $field !== '' ? $field : null,
                array_values($request->validated('column_map')),
            ),
            'merge_strategy' => $request->validated('merge_strategy'),
            'tag_names' => array_values($request->validated('tags', [])),
            'resubscribe_unsubscribed' => $contactImport->audience_id !== null && $request->boolean('resubscribe_unsubscribed'),
            'review_options' => [
                ...ContactImportIssue::defaultOptions(),
                ...Arr::only($request->validated('review_options', []), array_keys(ContactImportIssue::defaultOptions())),
            ],
            'consent_confirmed_at' => $contactImport->audience_id === null ? null : now(),
            'consent_ip' => $request->ip(),
            'status' => ContactImportStatus::Pending,
        ]);

        ProcessContactImport::dispatch($contactImport->id);

        return to_route('contacts.imports.show', [$currentTeam, $contactImport]);
    }

    public function retry(Team $currentTeam, ContactImport $contactImport): RedirectResponse
    {
        Gate::authorize('update', $contactImport);
        abort_unless(
            $contactImport->status === ContactImportStatus::Failed
                && Storage::disk($contactImport->disk)->exists($contactImport->path),
            409,
        );

        $contactImport->update([
            'status' => ContactImportStatus::Pending,
            'failure_message' => null,
            'completed_at' => null,
        ]);

        ProcessContactImport::dispatch($contactImport->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import restarted from where it stopped.')]);

        return back();
    }

    public function cancel(Team $currentTeam, ContactImport $contactImport): RedirectResponse
    {
        Gate::authorize('update', $contactImport);
        abort_unless($contactImport->status->isRunning(), 409);

        $contactImport->update([
            'status' => ContactImportStatus::Cancelled,
            'completed_at' => now(),
        ]);
        DB::table('contact_import_emails')->where('contact_import_id', $contactImport->id)->delete();
        Storage::disk($contactImport->disk)->delete($contactImport->path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import cancelled.')]);

        return back();
    }

    public function destroy(Team $currentTeam, ContactImport $contactImport): RedirectResponse
    {
        Gate::authorize('delete', $contactImport);
        abort_if($contactImport->status->isRunning(), 409);

        Storage::disk($contactImport->disk)->delete($contactImport->path);
        $contactImport->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import removed.')]);

        return to_route('contacts.imports.index', $currentTeam);
    }

    public function report(Team $currentTeam, ContactImport $contactImport): StreamedResponse
    {
        Gate::authorize('view', $contactImport);
        $fileName = pathinfo($contactImport->original_name, PATHINFO_FILENAME).'-report.csv';

        return response()->streamDownload(function () use ($contactImport): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['row', 'email', 'outcome', 'detail'], escape: '');

            foreach ($contactImport->rowErrors() as $error) {
                fputcsv($stream, [$error['row'], $error['email'], 'failed', $error['message']], escape: '');
            }

            foreach ($contactImport->review_flags ?? [] as $flag) {
                $issue = ContactImportIssue::tryFrom($flag['issue']);
                fputcsv($stream, [
                    $flag['row'],
                    $flag['email'],
                    $flag['action'],
                    trim(($issue?->label() ?? $flag['issue']).' '.($flag['detail'] ?? '')),
                ], escape: '');
            }

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int, Audience> */
    private function importableAudiences(Team $team): Collection
    {
        return $team->audiences()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'team_id'])
            ->each(fn (Audience $audience) => $audience->setRelation('team', $team))
            ->filter(fn (Audience $audience): bool => Gate::allows('create', [Subscriber::class, $audience]))
            ->values()
            ->toBase();
    }
}
