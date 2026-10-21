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
use Throwable;

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
     * Merge tags every campaign and automation email fills in itself. A custom
     * field with one of these keys would never be shown.
     *
     * @var list<string>
     */
    public const array BUILT_IN_MERGE_TAGS = [
        'name',
        'first_name',
        'last_name',
        'email',
        'language',
        'language_name',
        'day',
        'day_name',
        'month',
        'month_name',
        'year',
        'unsubscribe_url',
        'web_view_url',
        'subscribe_url',
    ];

    /**
     * Subscriber columns, built-in merge tags and other keys that custom
     * fields cannot reuse.
     *
     * @var list<string>
     */
    public const array RESERVED_KEYS = [
        ...self::BUILT_IN_MERGE_TAGS,
        'status',
        'source',
        'tags',
        'subscribed_at',
        'unsubscribed_at',
        'consent',
        'uuid',
        'id',
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

    /**
     * Rewrite submitted Date field values as Y-m-d, the format imports store,
     * so every reader can parse them. Values that are not a date are left for
     * validation to reject.
     *
     * @param  iterable<AudienceAttribute>  $attributes
     */
    public static function normalizeDateInput(mixed $values, iterable $attributes): mixed
    {
        if (! is_array($values)) {
            return $values;
        }

        foreach ($attributes as $attribute) {
            $value = $values[$attribute->key] ?? null;

            if ($attribute->type !== AudienceAttributeType::Date || ! is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = date_parse($value);

            if (strtotime($value) === false
                || ! is_int($parts['year']) || ! is_int($parts['month']) || ! is_int($parts['day'])
                || ! checkdate($parts['month'], $parts['day'], $parts['year'])) {
                continue;
            }

            try {
                $values[$attribute->key] = Carbon::parse($value)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return $values;
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
