<?php

namespace Database\Factories;

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
use App\Models\Audience;
use App\Models\SubscribeForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscribeForm> */
class SubscribeFormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'audience_id' => Audience::factory(),
            'name' => fake()->words(2, true),
            'headline' => 'Join our newsletter',
            'description' => fake()->sentence(),
            'text_alignment' => SubscribeFormTextAlignment::Center,
            'button_label' => 'Subscribe',
            'success_heading' => 'You’re subscribed!',
            'success_message' => 'Thanks for subscribing!',
            'redirect_enabled' => false,
            'redirect_url' => null,
            'powered_by_enabled' => true,
            'powered_by_form_position' => SubscribeFormPoweredByPosition::BottomCenter,
            'consent_text' => 'I agree to receive marketing emails.',
            'style' => SubscribeFormStyle::Card,
            'image_side' => SubscribeFormImageSide::Right,
            'artwork_type' => SubscribeFormArtworkType::Upload,
            'artwork_preset' => null,
            'image_url' => null,
            'logo_path' => null,
            'logo_shape' => SubscribeFormLogoShape::Default,
            'logo_size' => SubscribeFormLogoSize::Medium,
            'logo_position' => SubscribeFormLogoPosition::Center,
            'header_spacing' => SubscribeFormHeaderSpacing::Default,
            'card_padding' => SubscribeFormCardPadding::Default,
            'brand_color' => TeamBrandColor::Blue,
            'brand_font' => TeamBrandFont::Inter,
            'brand_input_style' => TeamBrandInputStyle::Default,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()]);
    }
}
