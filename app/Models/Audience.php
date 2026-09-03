<?php

namespace App\Models;

use App\Enums\SubscribeFormFieldMode;
use App\Services\DiceBearAvatarGenerator;
use Database\Factories\AudienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property SubscribeFormFieldMode $first_name_mode
 * @property SubscribeFormFieldMode $last_name_mode
 * @property bool $double_opt_in
 * @property int|null $double_opt_in_email_id
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $reply_to
 * @property string|null $notification_email
 * @property string|null $subscribed_url
 * @property string|null $already_subscribed_url
 * @property string|null $unsubscribed_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $subscribers_count
 * @property int $subscribed_count
 * @property int $segments_count
 * @property int $subscribe_forms_count
 * @property-read string $avatar
 * @property-read Team $team
 * @property-read Collection<int, AudienceAttribute> $audienceAttributes
 * @property-read Collection<int, ContactImport> $contactImports
 * @property-read TransactionalEmail|null $doubleOptInEmail
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'first_name_mode',
    'last_name_mode',
    'double_opt_in',
    'double_opt_in_email_id',
    'from_name',
    'from_address',
    'reply_to',
    'notification_email',
    'subscribed_url',
    'already_subscribed_url',
    'unsubscribed_url',
])]
class Audience extends Model
{
    /** @var list<string> */
    protected $appends = ['avatar'];

    /** @use HasFactory<AudienceFactory> */
    use HasFactory;

    protected $attributes = [
        'first_name_mode' => SubscribeFormFieldMode::Optional->value,
        'last_name_mode' => SubscribeFormFieldMode::Optional->value,
        'double_opt_in' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Audience $audience): void {
            $audience->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TransactionalEmail, $this> */
    public function doubleOptInEmail(): BelongsTo
    {
        return $this->belongsTo(TransactionalEmail::class, 'double_opt_in_email_id');
    }

    /** @return HasMany<Subscriber, $this> */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /** @return HasMany<ContactImport, $this> */
    public function contactImports(): HasMany
    {
        return $this->hasMany(ContactImport::class);
    }

    /** @return HasMany<Segment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    /** @return HasMany<SubscribeForm, $this> */
    public function subscribeForms(): HasMany
    {
        return $this->hasMany(SubscribeForm::class);
    }

    /** @return HasMany<AudienceAttribute, $this> */
    public function audienceAttributes(): HasMany
    {
        return $this->hasMany(AudienceAttribute::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'first_name_mode' => SubscribeFormFieldMode::class,
            'last_name_mode' => SubscribeFormFieldMode::class,
            'double_opt_in' => 'boolean',
        ];
    }

    /** @return Attribute<string, never> */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => URL::signedRoute('avatars.show', [
                'style' => DiceBearAvatarGenerator::SHAPE_GRID,
                'seed' => (string) ($attributes['uuid'] ?? 'maildun-audience'),
            ], absolute: false),
        );
    }
}
