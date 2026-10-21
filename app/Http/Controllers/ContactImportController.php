<?php

namespace App\Http\Controllers;

use App\Concerns\SanitizesSpreadsheetCells;
use App\Enums\ContactImportIssue;
use App\Enums\ContactImportStatus;
use App\Enums\StorageBackend;
use App\Enums\SubscriberLanguage;
use App\Exceptions\ContactImportFailed;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportController extends Controller
{
    use SanitizesSpreadsheetCells;

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
        } catch (ContactImportFailed $exception) {
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
            'column_map' => $csv->suggestMapping($analysis['headers'], $attributeKeys, $audience !== null),
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
        $fields = $isDraft ? $csv->fieldOptions($contactImport->audience) : [];
        $fieldValues = array_column($fields, 'value');

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
                // A suggested field can disappear after upload (a deleted audience
                // attribute), so offer it unmapped rather than as a value Start rejects.
                'columnMap' => array_map(
                    fn (?string $field): ?string => in_array($field, $fieldValues, true) ? $field : null,
                    $contactImport->column_map ?? [],
                ),
                'fields' => $fields,
                'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
                'languages' => SubscriberLanguage::options(),
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
            'language' => $contactImport->audience_id === null ? null : $request->validated('language'),
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

            // Excel only reads the file as UTF-8 with a byte order mark.
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['row', 'email', 'outcome', 'detail'], escape: '');

            // Emails and details come from the uploaded file, so neutralise formulas.
            foreach ($this->reportRows($contactImport) as $row) {
                fputcsv($stream, $this->sanitizeCsvRow($row), escape: '');
            }

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Every failed row, then every flagged row. Imports that ran before issues
     * were stored row by row fall back to the preview kept on the import.
     *
     * @return iterable<int, array{0: int|null, 1: string|null, 2: string, 3: string}>
     */
    private function reportRows(ContactImport $contactImport): iterable
    {
        $issues = DB::table('contact_import_issues')->where('contact_import_id', $contactImport->id);

        if (! $issues->exists()) {
            foreach ($contactImport->rowErrors() as $error) {
                yield [$error['row'], $error['email'], 'failed', $error['message']];
            }

            foreach ($contactImport->review_flags ?? [] as $flag) {
                yield [$flag['row'], $flag['email'], $flag['action'], $this->flagDetail($flag['issue'], $flag['detail'])];
            }

            return;
        }

        foreach ([true, false] as $failed) {
            $rows = $failed
                ? (clone $issues)->whereNull('issue')->lazyById(1000)
                : (clone $issues)->whereNotNull('issue')->lazyById(1000);

            foreach ($rows as $row) {
                yield [
                    $row->row_number === null ? null : (int) $row->row_number,
                    $row->email,
                    $row->outcome,
                    $failed ? (string) $row->detail : $this->flagDetail((string) $row->issue, $row->detail),
                ];
            }
        }
    }

    private function flagDetail(string $issue, ?string $detail): string
    {
        return trim((ContactImportIssue::tryFrom($issue)?->label() ?? $issue).' '.($detail ?? ''));
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
