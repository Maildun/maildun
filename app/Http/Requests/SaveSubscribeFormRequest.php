<?php

namespace App\Http\Requests;

use App\Enums\SubscribeFormImageSide;
use App\Enums\SubscribeFormLogoShape;
use App\Enums\SubscribeFormLogoSize;
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
            'success_heading' => ['required', 'string', 'max:255'],
            'success_message' => ['required', 'string', 'max:500'],
            'consent_text' => ['required', 'string', 'max:1000'],
            'style' => ['sometimes', Rule::enum(SubscribeFormStyle::class)],
            'image_side' => ['sometimes', Rule::enum(SubscribeFormImageSide::class)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_shape' => ['sometimes', Rule::enum(SubscribeFormLogoShape::class)],
            'logo_size' => ['sometimes', Rule::enum(SubscribeFormLogoSize::class)],
            'remove_logo' => ['sometimes', 'boolean'],
            'brand_color' => ['sometimes', Rule::enum(TeamBrandColor::class)],
            'brand_font' => ['sometimes', Rule::enum(TeamBrandFont::class)],
            'brand_input_style' => ['sometimes', Rule::enum(TeamBrandInputStyle::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.max' => __('Artwork must be 2 MB or smaller.'),
            'logo.max' => __('Logo must be 2 MB or smaller.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formAttributes(): array
    {
        return $this->safe()->except(['image', 'remove_image', 'logo', 'remove_logo']);
    }
}
