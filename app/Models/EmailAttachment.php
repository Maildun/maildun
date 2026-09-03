<?php

namespace App\Models;

use Database\Factories\EmailAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $email_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Email $email
 */
#[Fillable([
    'email_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class EmailAttachment extends Model
{
    /** @use HasFactory<EmailAttachmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (EmailAttachment $attachment): void {
            $attachment->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Email, $this> */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
