<?php

namespace App\Models;

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
 * @property string $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $imported_contacts
 * @property int $imported_subscribers
 * @property int $duplicate_rows
 * @property int $failed_rows
 * @property list<string>|null $errors
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
    'status',
    'total_rows',
    'processed_rows',
    'imported_contacts',
    'imported_subscribers',
    'duplicate_rows',
    'failed_rows',
    'errors',
    'completed_at',
])]
class ContactImport extends Model
{
    /** @use HasFactory<ContactImportFactory> */
    use HasFactory;

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
            'errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return array{
     *     uuid: string,
     *     file_name: string,
     *     status: string,
     *     processed_rows: int,
     *     imported_contacts: int,
     *     imported_subscribers: int,
     *     duplicate_rows: int,
     *     failed_rows: int,
     *     errors: list<string>,
     *     created_at: string|null,
     *     completed_at: string|null
     * }
     */
    public function toInertia(): array
    {
        return [
            'uuid' => $this->uuid,
            'file_name' => $this->original_name,
            'status' => $this->status,
            'processed_rows' => $this->processed_rows,
            'imported_contacts' => $this->imported_contacts,
            'imported_subscribers' => $this->imported_subscribers,
            'duplicate_rows' => $this->duplicate_rows,
            'failed_rows' => $this->failed_rows,
            'errors' => $this->errors ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
