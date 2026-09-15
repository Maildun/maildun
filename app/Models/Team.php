<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\EmailEditor;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Enums\TeamRole;
use App\Services\DiceBearAvatarGenerator;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property EmailEditor $email_editor
 * @property string|null $email_from_name
 * @property string|null $email_from_address
 * @property string|null $email_reply_to
 * @property int|null $active_sender_id
 * @property TeamBrandColor $brand_color
 * @property TeamBrandFont $brand_font
 * @property TeamBrandInputStyle $brand_input_style
 * @property bool $convert_uploads_to_webp
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $logo
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Audience> $audiences
 * @property-read Collection<int, Contact> $contacts
 * @property-read Collection<int, ContactImport> $contactImports
 * @property-read Collection<int, Company> $companies
 * @property-read Collection<int, Subscriber> $subscribers
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, Email> $emails
 * @property-read Collection<int, EmailTemplate> $emailTemplates
 * @property-read Collection<int, TransactionalEmail> $transactionalEmails
 * @property-read TeamEmailIntegration|null $emailIntegration
 * @property-read TeamSender|null $activeSender
 * @property-read Collection<int, TeamApiKey> $apiKeys
 * @property-read Collection<int, TransactionalEmailDelivery> $transactionalEmailDeliveries
 * @property-read Collection<int, AutomationEmailDelivery> $automationEmailDeliveries
 * @property-read Collection<int, EmailAddressHealth> $emailAddressHealths
 * @property-read Collection<int, Media> $media
 * @property-read Collection<int, MediaCategory> $mediaCategories
 * @property-read Collection<int, MediaTag> $mediaTags
 * @property-read Collection<int, Automation> $automations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable([
    'name',
    'slug',
    'logo_path',
    'is_personal',
    'email_editor',
    'email_from_name',
    'email_from_address',
    'email_reply_to',
    'brand_color',
    'brand_font',
    'brand_input_style',
    'convert_uploads_to_webp',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Append the public logo URL to serialized team data.
     *
     * @var list<string>
     */
    protected $appends = ['logo'];

    /**
     * Mirrors the column default so a freshly built team reports its editor
     * without having to be reloaded from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'email_editor' => EmailEditor::Html->value,
        'brand_color' => TeamBrandColor::Blue->value,
        'brand_font' => TeamBrandFont::Inter->value,
        'brand_input_style' => TeamBrandInputStyle::Default->value,
        'convert_uploads_to_webp' => false,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            $team->uuid ??= (string) Str::uuid();

            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the public URL for the team's uploaded or generated logo.
     *
     * @return Attribute<string, never>
     */
    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => filled($attributes['logo_path'] ?? null)
                ? Storage::disk('public')->url($attributes['logo_path'])
                : URL::signedRoute('avatars.show', [
                    'style' => DiceBearAvatarGenerator::LOOPS,
                    'seed' => (string) ($attributes['uuid'] ?? 'maildun-team'),
                ], absolute: false),
        );
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the permission roles that belong to this workspace.
     *
     * @return HasMany<WorkspaceRole, $this>
     */
    public function workspaceRoles(): HasMany
    {
        return $this->hasMany(WorkspaceRole::class, 'team_id');
    }

    /**
     * Ensure every workspace has its protected and default roles.
     */
    public function ensureDefaultRoles(): void
    {
        foreach (TeamRole::cases() as $role) {
            $workspaceRole = $this->workspaceRoles()->firstOrCreate(
                ['name' => $role->value, 'guard_name' => 'web'],
                ['label' => $role->label(), 'is_system' => true],
            );

            if ($workspaceRole->wasRecentlyCreated) {
                $workspaceRole->syncPermissions(WorkspaceRole::defaultPermissions($role));
            }
        }
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @return HasMany<Audience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(Audience::class);
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasMany<ContactImport, $this> */
    public function contactImports(): HasMany
    {
        return $this->hasMany(ContactImport::class);
    }

    /** @return HasMany<Company, $this> */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /** @return HasManyThrough<Subscriber, Audience, $this> */
    public function subscribers(): HasManyThrough
    {
        return $this->hasManyThrough(Subscriber::class, Audience::class);
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /** @return HasMany<Email, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /** @return HasMany<EmailTemplate, $this> */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /** @return HasMany<TransactionalEmail, $this> */
    public function transactionalEmails(): HasMany
    {
        return $this->hasMany(TransactionalEmail::class);
    }

    /** @return HasOne<TeamEmailIntegration, $this> */
    public function emailIntegration(): HasOne
    {
        return $this->hasOne(TeamEmailIntegration::class);
    }

    /**
     * Laravel's scoped route binding pluralizes the {emailIntegration}
     * parameter. The relationship remains one-to-one and is database-enforced.
     *
     * @return HasOne<TeamEmailIntegration, $this>
     */
    public function emailIntegrations(): HasOne
    {
        return $this->emailIntegration();
    }

    /** @return BelongsTo<TeamSender, $this> */
    public function activeSender(): BelongsTo
    {
        return $this->belongsTo(TeamSender::class, 'active_sender_id');
    }

    /** @return HasMany<TeamSender, $this> */
    public function senders(): HasMany
    {
        return $this->hasMany(TeamSender::class);
    }

    /** @return HasMany<TeamSenderDomain, $this> */
    public function senderDomains(): HasMany
    {
        return $this->hasMany(TeamSenderDomain::class);
    }

    public function resolvedEmailFromAddress(): ?string
    {
        $address = $this->email_from_address;

        return is_string($address) && filled($address) ? trim($address) : null;
    }

    public function hasVerifiedSenderAddress(?string $address): bool
    {
        if (blank($address)) {
            return false;
        }

        $integration = $this->emailIntegration()->first();

        return $integration instanceof TeamEmailIntegration
            && $integration->isSenderAuthorizedFor($address);
    }

    /** @return HasMany<TeamSender, $this> */
    public function verifiedSenders(): HasMany
    {
        $integration = $this->emailIntegration()->first();
        $query = $this->senders();

        if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->authorizedForIntegration($integration);
    }

    /** @return HasMany<TeamApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(TeamApiKey::class);
    }

    /** @return HasMany<TransactionalEmailDelivery, $this> */
    public function transactionalEmailDeliveries(): HasMany
    {
        return $this->hasMany(TransactionalEmailDelivery::class);
    }

    /** @return HasMany<AutomationEmailDelivery, $this> */
    public function automationEmailDeliveries(): HasMany
    {
        return $this->hasMany(AutomationEmailDelivery::class);
    }

    /** @return HasMany<EmailAddressHealth, $this> */
    public function emailAddressHealths(): HasMany
    {
        return $this->hasMany(EmailAddressHealth::class);
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /** @return HasMany<MediaCategory, $this> */
    public function mediaCategories(): HasMany
    {
        return $this->hasMany(MediaCategory::class);
    }

    /** @return HasMany<MediaTag, $this> */
    public function mediaTags(): HasMany
    {
        return $this->hasMany(MediaTag::class);
    }

    /** @return HasMany<Automation, $this> */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'email_editor' => EmailEditor::class,
            'brand_color' => TeamBrandColor::class,
            'brand_font' => TeamBrandFont::class,
            'brand_input_style' => TeamBrandInputStyle::class,
            'convert_uploads_to_webp' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
