<?php

namespace App\Http\Requests;

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
use App\Models\SubscribeForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSubscribeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscribeForm = $this->route('subscribeForm');

        return $subscribeForm instanceof SubscribeForm
            ? Gate::allows('update', $subscribeForm)
            : Gate::allows('create', [SubscribeForm::class, $this->route('audience')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'headline' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'text_alignment' => ['sometimes', Rule::enum(SubscribeFormTextAlignment::class)],
            'button_label' => ['required', 'string', 'max:80'],
            'success_heading' => ['sometimes', 'required', 'string', 'max:255'],
            'success_message' => ['required', 'string', 'max:500'],
            'redirect_enabled' => ['sometimes', 'boolean'],
            'redirect_url' => ['nullable', 'required_if:redirect_enabled,true', 'url:http,https', 'max:2048'],
            'powered_by_form_position' => ['sometimes', Rule::enum(SubscribeFormPoweredByPosition::class)],
            'consent_text' => ['required', 'string', 'max:1000'],
            'style' => ['sometimes', Rule::enum(SubscribeFormStyle::class)],
            'image_side' => ['sometimes', Rule::enum(SubscribeFormImageSide::class)],
            'artwork_type' => ['sometimes', Rule::enum(SubscribeFormArtworkType::class)],
            'artwork_preset' => ['nullable', Rule::enum(SubscribeFormArtworkPreset::class)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_shape' => ['sometimes', Rule::enum(SubscribeFormLogoShape::class)],
            'logo_size' => ['sometimes', Rule::enum(SubscribeFormLogoSize::class)],
            'logo_position' => ['sometimes', Rule::enum(SubscribeFormLogoPosition::class)],
            'header_spacing' => ['sometimes', Rule::enum(SubscribeFormHeaderSpacing::class)],
            'card_padding' => ['sometimes', Rule::enum(SubscribeFormCardPadding::class)],
            'remove_logo' => ['sometimes', 'boolean'],
            'brand_color' => ['sometimes', Rule::enum(TeamBrandColor::class)],
            'brand_font' => ['sometimes', Rule::enum(TeamBrandFont::class)],
            'brand_input_style' => ['sometimes', Rule::enum(TeamBrandInputStyle::class)],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('artwork_type') || $validator->errors()->has('artwork_preset')) {
                return;
            }

            $artworkType = SubscribeFormArtworkType::tryFrom($this->string('artwork_type')->toString());

            if ($artworkType === null || $artworkType === SubscribeFormArtworkType::Upload) {
                return;
            }

            $artworkPreset = SubscribeFormArtworkPreset::tryFrom($this->string('artwork_preset')->toString());

            if ($artworkPreset === null) {
                $validator->errors()->add('artwork_preset', __('Choose an artwork preset.'));

                return;
            }

            if ($artworkPreset->artworkType() !== $artworkType) {
                $validator->errors()->add('artwork_preset', __('The selected artwork preset does not match its type.'));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.max' => __('Artwork must be 2 MB or smaller.'),
            'logo.max' => __('Logo must be 2 MB or smaller.'),
            'redirect_url.required_if' => __('Enter the page subscribers should be redirected to.'),
            'redirect_url.url' => __('Enter a valid URL beginning with http:// or https://.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formAttributes(): array
    {
        $attributes = $this->safe()->except(['image', 'remove_image', 'logo', 'remove_logo', 'publish']);
        $attributes['powered_by_enabled'] = true;

        if (array_key_exists('redirect_url', $attributes) && blank($attributes['redirect_url'])) {
            $attributes['redirect_url'] = null;
        }

        return $attributes;
    }
}
