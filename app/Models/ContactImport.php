<?php

namespace App\Models;

use App\Enums\ContactImportIssue;
use App\Enums\ContactImportMergeStrategy;
use App\Enums\ContactImportStatus;
use Database\Factories\ContactImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $audience_id
 * @property int|null $uploaded_by
 * @property string|null $consent_ip
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string $delimiter
 * @property list<string>|null $headers
 * @property list<list<string>>|null $sample_rows
 * @property list<string|null>|null $column_map
 * @property ContactImportMergeStrategy $merge_strategy
 * @property list<string>|null $tag_names
 * @property bool $resubscribe_unsubscribed
 * @property array<string, string>|null $review_options
 * @property Carbon|null $consent_confirmed_at
 * @property ContactImportStatus $status
 * @property int $total_rows
 * @property int $file_offset
 * @property int $last_row_number
 * @property int $processed_rows
 * @property int $imported_contacts
 * @property int $updated_contacts
 * @property int $imported_subscribers
 * @property int $duplicate_rows
 * @property int $skipped_unsubscribed
 * @property int $flagged_rows
 * @property int $skipped_rows
 * @property array<string, int>|null $review_counts
 * @property int $failed_rows
 * @property list<array{row: int|null, email: string|null, message: string}|string>|null $errors
 * @property list<array{row: int, email: string, issue: string, action: string, detail: string|null}>|null $review_flags
 * @property string|null $failure_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Audience|null $audience
 * @property-read User|null $uploadedBy
 */
#[Fillable([
    'team_id',
    'audience_id',
    'uploaded_by',
    'consent_ip',
    'original_name',
    'disk',
    'path',
    'delimiter',
    'headers',
    'sample_rows',
    'column_map',
    'merge_strategy',
    'tag_names',
    'resubscribe_unsubscribed',
    'review_options',
    'consent_confirmed_at',
    'status',
    'total_rows',
    'file_offset',
    'last_row_number',
    'processed_rows',
    'imported_contacts',
    'updated_contacts',
    'imported_subscribers',
    'duplicate_rows',
    'skipped_unsubscribed',
    'flagged_rows',
    'skipped_rows',
    'review_counts',
    'failed_rows',
    'errors',
    'review_flags',
    'failure_message',
    'started_at',
    'completed_at',
])]
class ContactImport extends Model
{
    /** @use HasFactory<ContactImportFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ContactImportStatus::Pending->value,
        'merge_strategy' => ContactImportMergeStrategy::Skip->value,
        'delimiter' => ',',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContactImport $contactImport): void {
            $contactImport->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContactImportStatus::class,
            'merge_strategy' => ContactImportMergeStrategy::class,
            'file_offset' => 'integer',
            'headers' => 'array',
            'sample_rows' => 'array',
            'column_map' => 'array',
            'tag_names' => 'array',
            'resubscribe_unsubscribed' => 'boolean',
            'review_options' => 'array',
            'review_counts' => 'array',
            'review_flags' => 'array',
            'consent_confirmed_at' => 'datetime',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Row-level errors, normalizing messages stored before errors carried a row and email.
     *
     * @return list<array{row: int|null, email: string|null, message: string}>
     */
    public function rowErrors(): array
    {
        return array_map(
            fn (array|string $error): array => is_string($error)
                ? ['row' => null, 'email' => null, 'message' => $error]
                : $error,
            $this->errors ?? [],
        );
    }

    /**
     * @return array{
     *     uuid: string,
     *     file_name: string,
     *     status: string,
     *     status_label: string,
     *     audience: array{uuid: string, name: string}|null,
     *     uploaded_by: string|null,
     *     total_rows: int,
     *     processed_rows: int,
     *     imported_contacts: int,
     *     updated_contacts: int,
     *     imported_subscribers: int,
     *     duplicate_rows: int,
     *     skipped_unsubscribed: int,
     *     flagged_rows: int,
     *     skipped_rows: int,
     *     review_counts: array<string, int>,
     *     review_options: array<string, string>,
     *     failed_rows: int,
     *     error_count: int,
     *     failure_message: string|null,
     *     merge_strategy: string,
     *     tag_names: list<string>,
     *     resubscribe_unsubscribed: bool,
     *     created_at: string|null,
     *     started_at: string|null,
     *     updated_at: string|null,
     *     completed_at: string|null
     * }
     */
    public function toInertia(): array
    {
        return [
            'uuid' => $this->uuid,
            'file_name' => $this->original_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'audience' => $this->audience === null ? null : [
                'uuid' => $this->audience->uuid,
                'name' => $this->audience->name,
            ],
            'uploaded_by' => $this->uploadedBy?->name,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'imported_contacts' => $this->imported_contacts,
            'updated_contacts' => $this->updated_contacts,
            'imported_subscribers' => $this->imported_subscribers,
            'duplicate_rows' => $this->duplicate_rows,
            'skipped_unsubscribed' => $this->skipped_unsubscribed,
            'flagged_rows' => $this->flagged_rows,
            'skipped_rows' => $this->skipped_rows,
            'review_counts' => $this->review_counts ?? [],
            'review_options' => [...ContactImportIssue::defaultOptions(), ...($this->review_options ?? [])],
            'failed_rows' => $this->failed_rows,
            'error_count' => count($this->errors ?? []),
            'failure_message' => $this->failure_message,
            'merge_strategy' => $this->merge_strategy->value,
            'tag_names' => $this->tag_names ?? [],
            'resubscribe_unsubscribed' => $this->resubscribe_unsubscribed,
            'created_at' => $this->created_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
