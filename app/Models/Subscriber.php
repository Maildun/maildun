<?php

namespace App\Models;

use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Builders\SubscriberBuilder;
use App\Services\DiceBearAvatarGenerator;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
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
 * @property SubscriberLanguage|null $language
 * @property array<string, string|int|float> $attribute_values
 * @property SubscriberStatus $status
 * @property SubscriberSource $source
 * @property string|null $consent_text
 * @property Carbon|null $consented_at
 * @property string|null $consent_ip
 * @property Carbon|null $subscribed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $resubscribe_requested_at
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
    'language',
    'attribute_values',
    'status',
    'source',
    'consent_text',
    'consented_at',
    'consent_ip',
    'subscribed_at',
    'unsubscribed_at',
    'resubscribe_requested_at',
])]
#[UseEloquentBuilder(SubscriberBuilder::class)]
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

            $optOut = AudienceOptOut::query()
                ->where('audience_id', $subscriber->audience_id)
                ->where('email', $subscriber->email)
                ->first();

            // An address whose membership was deleted after it opted out comes
            // back unsubscribed, whatever path re-adds it, and with no consent
            // record of its own.
            if ($optOut instanceof AudienceOptOut) {
                $subscriber->forceFill([
                    'status' => SubscriberStatus::Unsubscribed,
                    'unsubscribed_at' => $optOut->unsubscribed_at ?? now(),
                    'subscribed_at' => null,
                    'resubscribe_requested_at' => null,
                    'consent_text' => null,
                    'consented_at' => null,
                    'consent_ip' => null,
                ]);
            }
        });

        static::created(function (Subscriber $subscriber): void {
            AudienceOptOut::query()
                ->where('audience_id', $subscriber->audience_id)
                ->where('email', $subscriber->email)
                ->delete();
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

    /**
     * The subscribed people a campaign may actually mail. Double opt-in
     * signups who never confirmed (subscribed_at is null) are skipped, as is
     * anyone the workspace suppressed after bounces or a complaint.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSendableFor(Builder $query, Team $team): void
    {
        $query
            ->optedIn()
            ->whereNotExists(
                EmailAddressHealth::query()
                    ->suppressedFor($team)
                    ->whereColumn('email_address_healths.email', 'subscribers.email'),
            );
    }

    /**
     * The one definition of a member who may be mailed: subscribed and, for
     * double opt-in, confirmed, with no opt-out kept from a deleted membership
     * of the same address. Campaign claims, retries and automations all
     * use it (or isOptedIn() in memory) so they can never disagree.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOptedIn(Builder $query): void
    {
        $query
            ->where('subscribers.status', SubscriberStatus::Subscribed)
            ->whereNotNull('subscribers.subscribed_at')
            ->whereNotExists(
                AudienceOptOut::query()
                    ->whereColumn('audience_opt_outs.audience_id', 'subscribers.audience_id')
                    ->whereColumn('audience_opt_outs.email', 'subscribers.email'),
            );
    }

    /**
     * The in-memory form of the optedIn scope.
     */
    public function isOptedIn(): bool
    {
        return $this->status === SubscriberStatus::Subscribed && $this->subscribed_at !== null;
    }

    /**
     * A double opt-in signup who has not clicked the confirmation link yet.
     */
    public function isPendingConfirmation(): bool
    {
        return $this->status === SubscriberStatus::Subscribed && $this->subscribed_at === null;
    }

    /**
     * A person who opted out and then asked to rejoin from a form or the API.
     * They stay unsubscribed until they click the confirmation link or a team
     * member resubscribes them.
     */
    public function isPendingResubscription(): bool
    {
        return $this->status === SubscriberStatus::Unsubscribed && $this->resubscribe_requested_at !== null;
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
            'language' => SubscriberLanguage::class,
            'attribute_values' => 'array',
            'consented_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'resubscribe_requested_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
