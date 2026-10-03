<?php

namespace App\Jobs;

use App\Actions\Audiences\AddContactToAudiences;
use App\Enums\AudienceAttributeType;
use App\Enums\AutomationTrigger;
use App\Enums\ContactImportIssue;
use App\Enums\ContactImportMergeStrategy;
use App\Enums\ContactImportStatus;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\AudienceAttribute;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EmailAddressHealth;
use App\Services\ContactImportCsv;
use App\Services\ImportEmailReview;
use App\Services\ManageContact;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    private const int CHUNK_SIZE = 200;

    private const int MAX_ERRORS = 500;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $contactImportId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-import:'.$this->contactImportId))->releaseAfter(5)->expireAfter(75)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(
        ManageContact $manageContact,
        AddContactToAudiences $addContactToAudiences,
        ContactImportCsv $csv,
        ImportEmailReview $emailReview,
    ): void {
        $contactImport = ContactImport::query()->with(['team', 'audience.audienceAttributes'])->find($this->contactImportId);

        if ($contactImport === null || ! $contactImport->status->isRunning()) {
            return;
        }

        $contactImport->update([
            'status' => ContactImportStatus::Processing,
            'started_at' => $contactImport->started_at ?? now(),
        ]);
        $stream = Storage::disk($contactImport->disk)->readStream($contactImport->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        $completed = false;

        try {
            $headers = $csv->readRow($stream, $contactImport->delimiter);

            if ($headers === false) {
                throw new RuntimeException('The CSV is empty.');
            }

            $fields = $this->fieldIndexes($contactImport->column_map ?? $csv->suggestMapping(array_map(
                fn (string $header, int $index): string => $index === 0 ? ltrim($header, "\xEF\xBB\xBF") : $header,
                $headers,
                array_keys($headers),
            )));

            if (! array_key_exists('email', $fields)) {
                throw new RuntimeException('The CSV must include an email column.');
            }

            $this->resume($stream, $contactImport->file_offset);
            $deadline = microtime(true) + 30;

            for ($rows = 0; $rows < self::CHUNK_SIZE && microtime(true) < $deadline; $rows++) {
                $row = $csv->readRow($stream, $contactImport->delimiter);

                if ($row === false) {
                    $completed = true;
                    break;
                }

                $offset = ftell($stream);

                if ($offset === false) {
                    throw new RuntimeException('The uploaded CSV position could not be recorded.');
                }

                DB::transaction(function () use ($contactImport, $row, $offset, $fields, $csv, $manageContact, $addContactToAudiences, $emailReview): void {
                    $contactImport->last_row_number++;

                    if (! $csv->isBlankRow($row)) {
                        $contactImport->processed_rows++;
                        $this->importRow($contactImport, $row, $fields, $manageContact, $addContactToAudiences, $emailReview);
                    }

                    $contactImport->file_offset = $offset;
                    $contactImport->total_rows = max($contactImport->total_rows, $contactImport->processed_rows);
                    $contactImport->save();
                });
            }

            if ($completed) {
                $contactImport->update([
                    'status' => ContactImportStatus::Completed,
                    'total_rows' => $contactImport->processed_rows,
                    'completed_at' => now(),
                ]);
                DB::table('contact_import_emails')->where('contact_import_id', $contactImport->id)->delete();
            }
        } finally {
            fclose($stream);
        }

        if ($completed) {
            Storage::disk($contactImport->disk)->delete($contactImport->path);
        } elseif (Queue::connection($this->connection) instanceof SyncQueue) {
            $this->handle($manageContact, $addContactToAudiences, $csv, $emailReview);
        } else {
            self::dispatch($contactImport->id)->afterCommit();
        }
    }

    /**
     * Keep the uploaded file so the import can be retried from its checkpoint.
     */
    public function failed(?Throwable $exception): void
    {
        $contactImport = ContactImport::query()->find($this->contactImportId);

        if ($contactImport === null || ! $contactImport->status->isRunning()) {
            return;
        }

        $contactImport->update([
            'status' => ContactImportStatus::Failed,
            'failure_message' => $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The import could not be completed.',
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, int>  $fields
     * @param  array<int, string>  $row
     */
    private function importRow(
        ContactImport $contactImport,
        array $row,
        array $fields,
        ManageContact $manageContact,
        AddContactToAudiences $addContactToAudiences,
        ImportEmailReview $emailReview,
    ): void {
        $email = Str::lower($this->valueAt($row, $fields['email']) ?? '');
        $firstName = $this->valueAt($row, $fields['first_name'] ?? null);
        $lastName = $this->valueAt($row, $fields['last_name'] ?? null);
        $tags = [
            ...($contactImport->tag_names ?? []),
            ...(preg_split('/[,;|]/', $this->valueAt($row, $fields['tags'] ?? null) ?? '', flags: PREG_SPLIT_NO_EMPTY) ?: []),
        ];
        $tags = array_values(array_filter(array_map('trim', $tags)));
        $error = $this->profileError($email, $firstName, $lastName, $tags);
        $attributes = [];

        if ($error === null && $contactImport->audience !== null) {
            [$attributes, $error] = $this->attributeValues($contactImport, $row, $fields);
        }

        if ($error !== null) {
            $contactImport->failed_rows++;
            $this->addError($contactImport, $email === '' ? null : $email, $error);

            return;
        }

        $email = $this->reviewEmail($contactImport, $email, $emailReview);

        if ($email === null) {
            $contactImport->skipped_rows++;

            return;
        }

        $fillBlanks = $contactImport->merge_strategy === ContactImportMergeStrategy::FillBlanks;
        $contact = $manageContact->findOrCreate($contactImport->team, [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $createdContact = $contact->wasRecentlyCreated;
        $updatedContact = false;
        $addedTags = [];

        if ($createdContact || $fillBlanks) {
            $blanks = $createdContact ? [] : array_filter([
                'first_name' => $contact->first_name === null ? $firstName : null,
                'last_name' => $contact->last_name === null ? $lastName : null,
            ], fn (?string $value): bool => $value !== null);

            if ($blanks !== []) {
                $contact = $manageContact->update($contact, $blanks);
                $updatedContact = true;
            }

            $addedTags = $manageContact->addTags($contactImport->team, $contact, $tags);
            $updatedContact = $updatedContact || (! $createdContact && $addedTags !== []);
        }

        [$createdMembership, $updatedMembership, $skippedUnsubscribed] = $contactImport->audience === null
            ? [0, false, false]
            : $this->syncMembership($contactImport, $contact, $attributes, $fillBlanks, $addContactToAudiences);

        $this->dispatchTagged($contact, $addedTags);

        $contactImport->imported_contacts += $createdContact ? 1 : 0;
        $contactImport->updated_contacts += ! $createdContact && ($updatedContact || $updatedMembership) ? 1 : 0;
        $contactImport->imported_subscribers += $createdMembership;
        $contactImport->skipped_unsubscribed += $skippedUnsubscribed ? 1 : 0;

        if (! $createdContact && ! $updatedContact && ! $updatedMembership && $createdMembership === 0 && ! $skippedUnsubscribed) {
            $contactImport->duplicate_rows++;
        }
    }

    /**
     * Run the local email checks, record what was found, and return the address to
     * import, or null when the row should be skipped.
     */
    private function reviewEmail(ContactImport $contactImport, string $email, ImportEmailReview $emailReview): ?string
    {
        $options = [...ContactImportIssue::defaultOptions(), ...($contactImport->review_options ?? [])];
        $domain = ImportEmailReview::domainOf($email);
        $flagged = false;
        $suggestion = $emailReview->suggestDomain($domain);

        if ($suggestion !== null && $emailReview->acceptsMail($domain) !== true) {
            $fixed = Str::beforeLast($email, '@').'@'.$suggestion;
            $action = $options[ContactImportIssue::Typo->value] === 'fix' && $emailReview->acceptsMail($domain) === false
                ? 'fixed'
                : ($options[ContactImportIssue::Typo->value] === 'skip' ? 'skipped' : 'imported');
            $this->addFlag($contactImport, ContactImportIssue::Typo, $email, $action, $fixed);

            if ($action === 'skipped') {
                return null;
            }

            if ($action === 'fixed') {
                $email = $fixed;
                $domain = $suggestion;
            }

            $flagged = true;
        }

        foreach ([
            [ContactImportIssue::Disposable, $emailReview->isDisposable($domain)],
            [ContactImportIssue::Undeliverable, ! $flagged && $emailReview->acceptsMail($domain) === false],
            [ContactImportIssue::RoleAddress, $emailReview->isRoleAddress($email)],
        ] as [$issue, $found]) {
            if (! $found) {
                continue;
            }

            $skip = $options[$issue->value] === 'skip';
            $this->addFlag($contactImport, $issue, $email, $skip ? 'skipped' : 'imported');

            if ($skip) {
                return null;
            }

            $flagged = true;
        }

        $inserted = DB::table('contact_import_emails')->insertOrIgnore([
            'contact_import_id' => $contactImport->id,
            'email' => $email,
            'row_number' => $contactImport->last_row_number + 1,
        ]);

        if ($inserted === 0) {
            $firstRow = DB::table('contact_import_emails')
                ->where('contact_import_id', $contactImport->id)
                ->where('email', $email)
                ->value('row_number');
            $this->addFlag($contactImport, ContactImportIssue::DuplicateInFile, $email, 'imported', is_numeric($firstRow) ? (string) $firstRow : null);
            $flagged = true;
        }

        if (EmailAddressHealth::query()->suppressedFor($contactImport->team)->where('email', $email)->exists()) {
            $this->addFlag($contactImport, ContactImportIssue::Suppressed, $email, 'imported');
            $flagged = true;
        }

        $contactImport->flagged_rows += $flagged ? 1 : 0;

        return $email;
    }

    private function addFlag(ContactImport $contactImport, ContactImportIssue $issue, string $email, string $action, ?string $detail = null): void
    {
        $counts = $contactImport->review_counts ?? [];
        $counts[$issue->value] = ($counts[$issue->value] ?? 0) + 1;
        $contactImport->review_counts = $counts;
        $flags = $contactImport->review_flags ?? [];

        if (count($flags) < self::MAX_ERRORS) {
            $flags[] = [
                'row' => $contactImport->last_row_number + 1,
                'email' => $email,
                'issue' => $issue->value,
                'action' => $action,
                'detail' => $detail,
            ];
            $contactImport->review_flags = $flags;
        }
    }

    /** @param list<string> $tags */
    private function profileError(string $email, ?string $firstName, ?string $lastName, array $tags): ?string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return __('Invalid email address.');
        }

        if (mb_strlen((string) $firstName) > 255 || mb_strlen((string) $lastName) > 255) {
            return __('Name is longer than 255 characters.');
        }

        if (collect($tags)->contains(fn (string $tag): bool => mb_strlen($tag) > 50)) {
            return __('Tag is longer than 50 characters.');
        }

        return null;
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{0: int, 1: bool, 2: bool}
     */
    private function syncMembership(
        ContactImport $contactImport,
        Contact $contact,
        array $attributes,
        bool $fillBlanks,
        AddContactToAudiences $addContactToAudiences,
    ): array {
        $audience = $contactImport->audience;
        $existing = $audience->subscribers()->where('email', $contact->email)->first();

        if ($existing?->status === SubscriberStatus::Unsubscribed && ! $contactImport->resubscribe_unsubscribed) {
            return [0, false, true];
        }

        $createdMembership = $addContactToAudiences->handle($contact, $audience->newCollection([$audience]), $contactImport->consent_ip);

        if ($attributes === [] || ($existing !== null && $createdMembership === 0 && ! $fillBlanks)) {
            return [$createdMembership, false, false];
        }

        $subscriber = $audience->subscribers()->where('email', $contact->email)->first();
        $current = $subscriber->attribute_values ?? [];
        $merged = [...$attributes, ...array_filter($current, fn (string|int|float $value): bool => $value !== '')];

        if ($merged === $current) {
            return [$createdMembership, false, false];
        }

        $subscriber->update(['attribute_values' => $merged]);

        return [$createdMembership, $existing !== null && $createdMembership === 0, false];
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $fields
     * @return array{0: array<string, string>, 1: string|null}
     */
    private function attributeValues(ContactImport $contactImport, array $row, array $fields): array
    {
        $values = [];

        /** @var AudienceAttribute $attribute */
        foreach ($contactImport->audience->audienceAttributes as $attribute) {
            $value = $this->valueAt($row, $fields['attribute:'.$attribute->key] ?? null);

            if ($value === null) {
                continue;
            }

            if ($attribute->type === AudienceAttributeType::Number && ! is_numeric($value)) {
                return [[], __(':field must be a number.', ['field' => $attribute->name])];
            }

            if ($attribute->type === AudienceAttributeType::Date) {
                try {
                    $value = Carbon::parse($value)->toDateString();
                } catch (Throwable) {
                    return [[], __(':field must be a date.', ['field' => $attribute->name])];
                }
            }

            if (mb_strlen($value) > 255) {
                return [[], __(':field is longer than 255 characters.', ['field' => $attribute->name])];
            }

            $values[$attribute->key] = $value;
        }

        return [$values, null];
    }

    /** @param list<string> $tagUuids */
    private function dispatchTagged(Contact $contact, array $tagUuids): void
    {
        if ($tagUuids === []) {
            return;
        }

        foreach ($contact->subscribers()->get() as $subscriber) {
            foreach ($tagUuids as $tagUuid) {
                event(new SubscriberLifecycleOccurred(AutomationTrigger::Tagged, $subscriber, ['tag_uuid' => $tagUuid]));
            }
        }
    }

    /**
     * @param  list<string|null>  $columnMap
     * @return array<string, int>
     */
    private function fieldIndexes(array $columnMap): array
    {
        $fields = [];

        foreach ($columnMap as $index => $field) {
            if (is_string($field) && $field !== '' && ! array_key_exists($field, $fields)) {
                $fields[$field] = $index;
            }
        }

        return $fields;
    }

    /** @param resource $stream */
    private function resume($stream, int $offset): void
    {
        if ($offset <= 0) {
            return;
        }

        $metadata = stream_get_meta_data($stream);

        if ($metadata['seekable']) {
            if (fseek($stream, $offset) !== 0) {
                throw new RuntimeException('The uploaded CSV file could not be resumed.');
            }

            return;
        }

        $remainingBytes = $offset - (int) ftell($stream);

        while ($remainingBytes > 0) {
            $skipped = fread($stream, min($remainingBytes, 1024 * 1024));

            if ($skipped === false || $skipped === '') {
                throw new RuntimeException('The uploaded CSV file could not be resumed.');
            }

            $remainingBytes -= strlen($skipped);
        }
    }

    /** @param array<int, string> $row */
    private function valueAt(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = trim($row[$index] ?? '');

        return $value === '' ? null : $value;
    }

    private function addError(ContactImport $contactImport, ?string $email, string $message): void
    {
        $errors = $contactImport->errors ?? [];

        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = ['row' => $contactImport->last_row_number + 1, 'email' => $email, 'message' => $message];
            $contactImport->errors = $errors;
        }
    }
}
