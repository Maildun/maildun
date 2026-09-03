<?php

namespace App\Jobs;

use App\Actions\Audiences\AddContactToAudiences;
use App\Models\ContactImport;
use App\Services\ManageContact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $contactImportId) {}

    public function handle(ManageContact $manageContact, AddContactToAudiences $addContactToAudiences): void
    {
        $contactImport = ContactImport::query()
            ->with(['team', 'audience'])
            ->find($this->contactImportId);

        if ($contactImport === null || $contactImport->status === 'completed') {
            return;
        }

        $contactImport->update([
            'status' => 'processing',
            'errors' => null,
        ]);

        $stream = Storage::disk($contactImport->disk)->readStream($contactImport->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $headers = $this->headers($stream);
            $emailIndex = array_search('email', $headers, true);

            if ($emailIndex === false) {
                throw new RuntimeException('The CSV must include an email column.');
            }

            $firstNameIndex = array_search('first_name', $headers, true);
            $lastNameIndex = array_search('last_name', $headers, true);
            $counts = [
                'processed_rows' => 0,
                'imported_contacts' => 0,
                'imported_subscribers' => 0,
                'duplicate_rows' => 0,
                'failed_rows' => 0,
            ];
            $errors = [];

            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $counts['processed_rows']++;
                $email = Str::lower(trim((string) ($row[$emailIndex] ?? '')));
                $firstName = $this->valueAt($row, $firstNameIndex);
                $lastName = $this->valueAt($row, $lastNameIndex);
                $rowNumber = $counts['processed_rows'] + 1;

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                    $counts['failed_rows']++;
                    $this->addError($errors, __('Row :row has an invalid email address.', ['row' => $rowNumber]));
                    $this->persistProgress($contactImport, $counts, $errors);

                    continue;
                }

                if (($firstName !== null && mb_strlen($firstName) > 255)
                    || ($lastName !== null && mb_strlen($lastName) > 255)) {
                    $counts['failed_rows']++;
                    $this->addError($errors, __('Row :row has a name longer than 255 characters.', ['row' => $rowNumber]));
                    $this->persistProgress($contactImport, $counts, $errors);

                    continue;
                }

                $contact = $manageContact->findOrCreate($contactImport->team, [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
                $createdContact = $contact->wasRecentlyCreated;
                $createdMembership = 0;

                if ($contactImport->audience !== null) {
                    $createdMembership = $addContactToAudiences->handle(
                        $contact,
                        new Collection([$contactImport->audience]),
                        $contactImport->consent_ip,
                    );
                }

                $counts['imported_contacts'] += $createdContact ? 1 : 0;
                $counts['imported_subscribers'] += $createdMembership;

                if (! $createdContact && $createdMembership === 0) {
                    $counts['duplicate_rows']++;
                }

                $this->persistProgress($contactImport, $counts, $errors);
            }

            $contactImport->update([
                ...$counts,
                'total_rows' => $counts['processed_rows'],
                'status' => 'completed',
                'errors' => $errors === [] ? null : $errors,
                'completed_at' => now(),
            ]);
        } finally {
            fclose($stream);
            Storage::disk($contactImport->disk)->delete($contactImport->path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $contactImport = ContactImport::query()->find($this->contactImportId);

        if ($contactImport === null) {
            return;
        }

        $contactImport->update([
            'status' => 'failed',
            'errors' => [$exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The import could not be completed.'],
            'completed_at' => now(),
        ]);

        Storage::disk($contactImport->disk)->delete($contactImport->path);
    }

    /** @param resource $stream
     * @return list<string>
     */
    private function headers($stream): array
    {
        $row = fgetcsv($stream, null, ',', '"', '');

        if (! is_array($row)) {
            throw new RuntimeException('The CSV is empty.');
        }

        return array_map(function (mixed $header, int $index): string {
            $value = trim((string) $header);

            if ($index === 0) {
                $value = ltrim($value, "\xEF\xBB\xBF");
            }

            return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        }, $row, array_keys($row));
    }

    /** @param array<int, string|null> $row */
    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    /** @param array<int, string|null> $row */
    private function valueAt(array $row, int|false $index): ?string
    {
        if ($index === false) {
            return null;
        }

        $value = trim((string) ($row[$index] ?? ''));

        return $value === '' ? null : $value;
    }

    /** @param list<string> $errors */
    private function addError(array &$errors, string $message): void
    {
        if (count($errors) < 20) {
            $errors[] = $message;
        }
    }

    /**
     * @param  array{processed_rows: int, imported_contacts: int, imported_subscribers: int, duplicate_rows: int, failed_rows: int}  $counts
     * @param  list<string>  $errors
     */
    private function persistProgress(ContactImport $contactImport, array $counts, array $errors): void
    {
        if ($counts['processed_rows'] % 100 !== 0) {
            return;
        }

        $contactImport->update([
            ...$counts,
            'total_rows' => $counts['processed_rows'],
            'errors' => $errors === [] ? null : $errors,
        ]);
    }
}
