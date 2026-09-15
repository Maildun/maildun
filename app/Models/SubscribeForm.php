<?php

namespace App\Models;

use App\Enums\SubscribeFormArtworkPreset;
use App\Enums\SubscribeFormArtworkType;
use App\Enums\SubscribeFormCardPadding;
use App\Enums\SubscribeFormHeaderSpacing;
use App\Enums\SubscribeFormImageSide;
use App\Enums\SubscribeFormLogoPosition;
use App\Enums\SubscribeFormLogoShape;
use App\Enums\SubscribeFormLogoSize;
use App\Enums\SubscribeFormPoweredByPosition;
use App\Enums\SubscribeFormStyle;
use App\Enums\SubscribeFormTextAlignment;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use Database\Factories\SubscribeFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $audience_id
 * @property string $name
 * @property string $headline
 * @property string|null $description
 * @property SubscribeFormTextAlignment $text_alignment
 * @property string $button_label
 * @property string $success_heading
 * @property string $success_message
 * @property bool $redirect_enabled
 * @property string|null $redirect_url
 * @property bool $powered_by_enabled
 * @property SubscribeFormPoweredByPosition $powered_by_form_position
 * @property string $consent_text
 * @property SubscribeFormStyle $style
 * @property SubscribeFormImageSide $image_side
 * @property SubscribeFormArtworkType $artwork_type
 * @property SubscribeFormArtworkPreset|null $artwork_preset
 * @property string|null $image_url
 * @property string|null $image_path
 * @property string|null $image_upload_path
 * @property string|null $image_upload_disk
 * @property string|null $logo_path
 * @property SubscribeFormLogoShape $logo_shape
 * @property SubscribeFormLogoSize $logo_size
 * @property SubscribeFormLogoPosition $logo_position
 * @property SubscribeFormHeaderSpacing $header_spacing
 * @property SubscribeFormCardPadding $card_padding
 * @property TeamBrandColor $brand_color
 * @property TeamBrandFont $brand_font
 * @property TeamBrandInputStyle $brand_input_style
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int $subscribers_count
 * @property-read string|null $image
 * @property-read string|null $logo
 * @property-read Audience $audience
 */
#[Fillable([
    'audience_id',
    'name',
    'headline',
    'description',
    'text_alignment',
    'button_label',
    'success_heading',
    'success_message',
    'redirect_enabled',
    'redirect_url',
    'powered_by_enabled',
    'powered_by_form_position',
    'consent_text',
    'style',
    'image_side',
    'artwork_type',
    'artwork_preset',
    'image_url',
    'image_path',
    'image_upload_path',
    'image_upload_disk',
    'logo_path',
    'logo_shape',
    'logo_size',
    'logo_position',
    'header_spacing',
    'card_padding',
    'brand_color',
    'brand_font',
    'brand_input_style',
    'published_at',
])]
class SubscribeForm extends Model
{
    /** @use HasFactory<SubscribeFormFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $appends = ['image', 'logo'];

    protected $attributes = [
        'button_label' => 'Subscribe',
        'success_heading' => 'You’re subscribed!',
        'success_message' => 'Thanks for subscribing!',
        'redirect_enabled' => false,
        'powered_by_enabled' => true,
        'powered_by_form_position' => SubscribeFormPoweredByPosition::BottomCenter->value,
        'style' => SubscribeFormStyle::Card->value,
        'text_alignment' => SubscribeFormTextAlignment::Center->value,
        'image_side' => SubscribeFormImageSide::Right->value,
        'artwork_type' => SubscribeFormArtworkType::Upload->value,
        'logo_shape' => SubscribeFormLogoShape::Default->value,
        'logo_size' => SubscribeFormLogoSize::Medium->value,
        'logo_position' => SubscribeFormLogoPosition::Center->value,
        'header_spacing' => SubscribeFormHeaderSpacing::Default->value,
        'card_padding' => SubscribeFormCardPadding::Default->value,
        'brand_color' => TeamBrandColor::Blue->value,
        'brand_font' => TeamBrandFont::Inter->value,
        'brand_input_style' => TeamBrandInputStyle::Default->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (SubscribeForm $subscribeForm): void {
            $subscribeForm->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return HasMany<Subscriber, $this> */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => filled($attributes['logo_path'] ?? null)
                ? Storage::disk('public')->url($attributes['logo_path'])
                : null,
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => filled($attributes['image_path'] ?? null)
                ? Storage::disk('public')->url($attributes['image_path'])
                : ($attributes['image_url'] ?? null),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'style' => SubscribeFormStyle::class,
            'text_alignment' => SubscribeFormTextAlignment::class,
            'image_side' => SubscribeFormImageSide::class,
            'artwork_type' => SubscribeFormArtworkType::class,
            'artwork_preset' => SubscribeFormArtworkPreset::class,
            'logo_shape' => SubscribeFormLogoShape::class,
            'logo_size' => SubscribeFormLogoSize::class,
            'logo_position' => SubscribeFormLogoPosition::class,
            'header_spacing' => SubscribeFormHeaderSpacing::class,
            'card_padding' => SubscribeFormCardPadding::class,
            'brand_color' => TeamBrandColor::class,
            'brand_font' => TeamBrandFont::class,
            'brand_input_style' => TeamBrandInputStyle::class,
            'redirect_enabled' => 'boolean',
            'powered_by_enabled' => 'boolean',
            'powered_by_form_position' => SubscribeFormPoweredByPosition::class,
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
