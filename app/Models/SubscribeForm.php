<?php

namespace App\Models;

use App\Enums\SubscribeFormImageSide;
use App\Enums\SubscribeFormLogoShape;
use App\Enums\SubscribeFormLogoSize;
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
 * @property string $consent_text
 * @property SubscribeFormStyle $style
 * @property SubscribeFormImageSide $image_side
 * @property string|null $image_url
 * @property string|null $image_path
 * @property string|null $image_upload_path
 * @property string|null $image_upload_disk
 * @property string|null $logo_path
 * @property SubscribeFormLogoShape $logo_shape
 * @property SubscribeFormLogoSize $logo_size
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
    'consent_text',
    'style',
    'image_side',
    'image_url',
    'image_path',
    'image_upload_path',
    'image_upload_disk',
    'logo_path',
    'logo_shape',
    'logo_size',
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
        'style' => SubscribeFormStyle::Card->value,
        'text_alignment' => SubscribeFormTextAlignment::Center->value,
        'image_side' => SubscribeFormImageSide::Right->value,
        'logo_shape' => SubscribeFormLogoShape::Default->value,
        'logo_size' => SubscribeFormLogoSize::Medium->value,
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
            'logo_shape' => SubscribeFormLogoShape::class,
            'logo_size' => SubscribeFormLogoSize::class,
            'brand_color' => TeamBrandColor::class,
            'brand_font' => TeamBrandFont::class,
            'brand_input_style' => TeamBrandInputStyle::class,
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
