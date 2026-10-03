<?php

namespace App\Jobs;

use App\Actions\Audiences\AddContactToAudiences;
use App\Models\ContactImport;
use App\Services\ManageContact;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SyncQueue;
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

    public function handle(ManageContact $manageContact, AddContactToAudiences $addContactToAudiences): void
    {
        $contactImport = ContactImport::query()->with(['team', 'audience'])->find($this->contactImportId);

        if ($contactImport === null || in_array($contactImport->status, ['completed', 'failed'], true)) {
            return;
        }

        $contactImport->update(['status' => 'processing']);
        $stream = Storage::disk($contactImport->disk)->readStream($contactImport->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        $completed = false;

        try {
            $headers = $this->headers($stream);
            $emailIndex = array_search('email', $headers, true);

            if ($emailIndex === false) {
                throw new RuntimeException('The CSV must include an email column.');
            }

            if ($contactImport->file_offset > 0) {
                $metadata = stream_get_meta_data($stream);

                if ($metadata['seekable']) {
                    if (fseek($stream, $contactImport->file_offset) !== 0) {
                        throw new RuntimeException('The uploaded CSV file could not be resumed.');
                    }
                } else {
                    $remainingBytes = $contactImport->file_offset - (int) ftell($stream);

                    while ($remainingBytes > 0) {
                        $skipped = fread($stream, min($remainingBytes, 1024 * 1024));

                        if ($skipped === false || $skipped === '') {
                            throw new RuntimeException('The uploaded CSV file could not be resumed.');
                        }

                        $remainingBytes -= strlen($skipped);
                    }
                }
            }

            $firstNameIndex = array_search('first_name', $headers, true);
            $lastNameIndex = array_search('last_name', $headers, true);
            $deadline = microtime(true) + 30;

            for ($rows = 0; $rows < self::CHUNK_SIZE && microtime(true) < $deadline; $rows++) {
                $row = fgetcsv($stream, null, ',', '"', '');

                if ($row === false) {
                    $completed = true;
                    break;
                }

                $offset = ftell($stream);

                if ($offset === false) {
                    throw new RuntimeException('The uploaded CSV position could not be recorded.');
                }

                DB::transaction(function () use ($contactImport, $row, $offset, $emailIndex, $firstNameIndex, $lastNameIndex, $manageContact, $addContactToAudiences): void {
                    if (! $this->isBlankRow($row)) {
                        $contactImport->processed_rows++;
                        $email = Str::lower(trim((string) ($row[$emailIndex] ?? '')));
                        $firstName = $this->valueAt($row, $firstNameIndex);
                        $lastName = $this->valueAt($row, $lastNameIndex);
                        $rowNumber = $contactImport->processed_rows + 1;
                        $errors = $contactImport->errors ?? [];

                        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                            $contactImport->failed_rows++;
                            $this->addError($errors, __('Row :row has an invalid email address.', ['row' => $rowNumber]));
                        } elseif (($firstName !== null && mb_strlen($firstName) > 255) || ($lastName !== null && mb_strlen($lastName) > 255)) {
                            $contactImport->failed_rows++;
                            $this->addError($errors, __('Row :row has a name longer than 255 characters.', ['row' => $rowNumber]));
                        } else {
                            $contact = $manageContact->findOrCreate($contactImport->team, [
                                'email' => $email, 'first_name' => $firstName, 'last_name' => $lastName,
                            ]);
                            $createdContact = $contact->wasRecentlyCreated;
                            $createdMembership = $contactImport->audience === null ? 0 : $addContactToAudiences->handle(
                                $contact, new Collection([$contactImport->audience]), $contactImport->consent_ip,
                            );
                            $contactImport->imported_contacts += $createdContact ? 1 : 0;
                            $contactImport->imported_subscribers += $createdMembership;

                            if (! $createdContact && $createdMembership === 0) {
                                $contactImport->duplicate_rows++;
                            }
                        }

                        $contactImport->errors = $errors === [] ? null : $errors;
                    }

                    $contactImport->file_offset = $offset;
                    $contactImport->total_rows = $contactImport->processed_rows;
                    $contactImport->save();
                });
            }

            if ($completed) {
                $contactImport->update(['status' => 'completed', 'completed_at' => now()]);
            }
        } finally {
            fclose($stream);
        }

        if ($completed) {
            Storage::disk($contactImport->disk)->delete($contactImport->path);
        } elseif (Queue::connection($this->connection) instanceof SyncQueue) {
            $this->handle($manageContact, $addContactToAudiences);
        } else {
            self::dispatch($contactImport->id)->afterCommit();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $contactImport = ContactImport::query()->find($this->contactImportId);

        if ($contactImport === null || $contactImport->status === 'completed') {
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
}
