<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\StorageBackend;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $media_category_id
 * @property int|null $uploaded_by
 * @property string $name
 * @property string|null $alt
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $upload_path
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property MediaStatus $status
 * @property string|null $failed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read MediaCategory|null $category
 * @property-read Collection<int, MediaTag> $tags
 * @property-read User|null $uploader
 * @property-read string|null $url
 * @property-read string|null $absolute_url
 */
#[Fillable([
    'team_id',
    'media_category_id',
    'uploaded_by',
    'name',
    'alt',
    'disk',
    'path',
    'upload_path',
    'mime_type',
    'extension',
    'size',
    'width',
    'height',
    'status',
    'failed_reason',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MediaStatus::Ready->value,
        'size' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $media->uuid ??= (string) Str::uuid();
        });

        static::deleting(function (Media $media): void {
            $media->deleteStoredFiles();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<MediaCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class, 'media_category_id');
    }

    /** @return BelongsToMany<MediaTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class)->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Root-relative public URL for in-app thumbnails.
     *
     * @return Attribute<string|null, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => filled($this->path)
                ? Storage::disk('public')->url($this->path)
                : null,
        );
    }

    /**
     * Absolute URL on the current host, for copying into emails.
     *
     * @return Attribute<string|null, never>
     */
    protected function absoluteUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $url = $this->url;

                if ($url === null) {
                    return null;
                }

                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }

                return request()->getSchemeAndHttpHost().$url;
            },
        );
    }

    public function isProcessing(): bool
    {
        return $this->status === MediaStatus::Processing;
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    public function directory(): string
    {
        return 'media/'.$this->team->uuid;
    }

    public function pendingDirectory(): string
    {
        return $this->directory().'/pending';
    }

    /**
     * @return array{
     *     uuid: string,
     *     name: string,
     *     alt: string|null,
     *     url: string|null,
     *     absolute_url: string|null,
     *     mime_type: string|null,
     *     extension: string|null,
     *     size: int,
     *     size_label: string,
     *     width: int|null,
     *     height: int|null,
     *     status: string,
     *     failed_reason: string|null,
     *     processing: bool,
     *     created_at: string|null,
     *     category: array{uuid: string, name: string}|null,
     *     tags: list<array{uuid: string, name: string}>
     * }
     */
    public function toInertia(): array
    {
        $this->loadMissing(['category', 'tags']);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'alt' => $this->alt,
            'url' => $this->url,
            'absolute_url' => $this->absolute_url,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'size_label' => Number::fileSize($this->size),
            'width' => $this->width,
            'height' => $this->height,
            'status' => $this->status->value,
            'failed_reason' => $this->failed_reason,
            'processing' => $this->isProcessing(),
            'created_at' => $this->created_at?->toISOString(),
            'category' => $this->category === null ? null : [
                'uuid' => $this->category->uuid,
                'name' => $this->category->name,
            ],
            'tags' => array_values($this->tags
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (MediaTag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                ])
                ->all()),
        ];
    }

    public function deleteStoredFiles(): void
    {
        if (filled($this->path)) {
            Storage::disk('public')->delete($this->path);
        }

        if (filled($this->upload_path)) {
            Storage::disk($this->disk ?? StorageBackend::current()->privateDisk())->delete($this->upload_path);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
