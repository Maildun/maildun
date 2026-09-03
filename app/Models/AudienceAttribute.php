<?php

namespace App\Models;

use App\Enums\AudienceAttributeType;
use Database\Factories\AudienceAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $audience_id
 * @property string $name
 * @property string $key
 * @property AudienceAttributeType $type
 * @property bool $required
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 */
#[Fillable(['audience_id', 'name', 'key', 'type', 'required', 'position'])]
class AudienceAttribute extends Model
{
    /** @use HasFactory<AudienceAttributeFactory> */
    use HasFactory;

    /**
     * Subscriber columns and other built-in keys that custom fields cannot reuse.
     *
     * @var list<string>
     */
    public const array RESERVED_KEYS = [
        'email',
        'first_name',
        'last_name',
        'status',
        'source',
        'tags',
        'subscribed_at',
        'unsubscribed_at',
        'consent',
        'uuid',
        'id',
        'name',
    ];

    protected $attributes = [
        'type' => AudienceAttributeType::Text->value,
        'required' => false,
        'position' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (AudienceAttribute $attribute): void {
            $attribute->uuid ??= (string) Str::uuid();
            $attribute->key ??= static::keyFromName($attribute->name);

            if ($attribute->position === 0) {
                $attribute->position = (int) static::query()
                    ->where('audience_id', $attribute->audience_id)
                    ->max('position') + 1;
            }
        });
    }

    public static function keyFromName(string $name): string
    {
        $key = Str::of($name)->lower()->snake()->replaceMatches('/[^a-z0-9_]/', '')->trim('_')->toString();

        if ($key === '') {
            return 'field';
        }

        if (preg_match('/^[0-9]/', $key) === 1) {
            return 'field_'.$key;
        }

        return $key;
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AudienceAttributeType::class,
            'required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
