<?php

namespace App\Models;

use App\Enums\ContactCompanyAssignmentMode;
use App\Services\DiceBearAvatarGenerator;
use Database\Factories\ContactFactory;
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
 * @property int $team_id
 * @property int|null $company_id
 * @property ContactCompanyAssignmentMode $company_assignment_mode
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $avatar
 * @property-read Team $team
 * @property-read Company|null $company
 * @property-read Collection<int, Subscriber> $subscribers
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable([
    'team_id',
    'company_id',
    'company_assignment_mode',
    'email',
    'first_name',
    'last_name',
])]
class Contact extends Model
{
    /** @var list<string> */
    protected $appends = ['avatar'];

    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $attributes = [
        'company_assignment_mode' => ContactCompanyAssignmentMode::Automatic->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            $contact->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Subscriber, $this> */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** @return HasMany<EmailDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
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
                'seed' => (string) ($attributes['uuid'] ?? 'maildun-contact'),
            ], absolute: false),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_assignment_mode' => ContactCompanyAssignmentMode::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
