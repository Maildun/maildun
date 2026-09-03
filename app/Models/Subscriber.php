<?php

namespace App\Models;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Services\DiceBearAvatarGenerator;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $audience_id
 * @property int|null $contact_id
 * @property int|null $subscribe_form_id
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property array<string, string|int|float> $attribute_values
 * @property SubscriberStatus $status
 * @property SubscriberSource $source
 * @property string|null $consent_text
 * @property Carbon|null $consented_at
 * @property string|null $consent_ip
 * @property Carbon|null $subscribed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 * @property-read Contact|null $contact
 * @property-read string $avatar
 * @property-read SubscribeForm|null $subscribeForm
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, EmailDelivery> $deliveries
 * @property-read Collection<int, AutomationRun> $automationRuns
 * @property-read Collection<int, Segment> $segments
 */
#[Fillable([
    'audience_id',
    'contact_id',
    'subscribe_form_id',
    'email',
    'first_name',
    'last_name',
    'attribute_values',
    'status',
    'source',
    'consent_text',
    'consented_at',
    'consent_ip',
    'subscribed_at',
    'unsubscribed_at',
])]
class Subscriber extends Model
{
    /** @var list<string> */
    protected $appends = ['avatar'];

    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => SubscriberStatus::Subscribed->value,
        'source' => SubscriberSource::Manual->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber): void {
            $subscriber->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<SubscribeForm, $this> */
    public function subscribeForm(): BelongsTo
    {
        return $this->belongsTo(SubscribeForm::class)->withTrashed();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'contact_tag',
            'contact_id',
            'tag_id',
            'contact_id',
            'id',
        )->withTimestamps();
    }

    /** @return HasMany<EmailDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }

    /** @return HasMany<AutomationRun, $this> */
    public function automationRuns(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /** @return BelongsToMany<Segment, $this> */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class)->withTimestamps();
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(Str::of($value)->trim()->toString()),
        );
    }

    /** @return Attribute<string, never> */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => URL::signedRoute('avatars.show', [
                'style' => DiceBearAvatarGenerator::MICAH,
                'seed' => (string) ($attributes['uuid'] ?? 'maildun-subscriber'),
            ], absolute: false),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'source' => SubscriberSource::class,
            'attribute_values' => 'array',
            'consented_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
