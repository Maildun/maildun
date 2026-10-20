<?php

namespace App\Mcp\Support;

use App\Enums\SubscribeFormFieldMode;
use App\Enums\SubscriberLanguage;
use App\Models\Audience;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AudienceLanguageSettings
{
    public const array FIELDS = ['language_mode', 'allowed_languages', 'default_language'];

    /**
     * Input rules for the language fields; the combined settings are checked by resolve().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language_mode' => ['sometimes', Rule::enum(SubscribeFormFieldMode::class)],
            'allowed_languages' => ['sometimes', 'array'],
            'allowed_languages.*' => ['distinct', Rule::enum(SubscriberLanguage::class)],
            'default_language' => ['sometimes', 'nullable', Rule::enum(SubscriberLanguage::class)],
        ];
    }

    /**
     * Merge the given language fields over the audience's current settings and
     * hold the result to the same rules as the audience settings page: a visible
     * language field needs at least one allowed language and a default from them.
     *
     * @param  array<string, mixed>  $validated
     * @return array{language_mode?: string, allowed_languages?: list<string>, default_language?: string|null}
     */
    public function resolve(array $validated, ?Audience $audience = null): array
    {
        $given = array_intersect_key($validated, array_flip(self::FIELDS));

        if ($given === []) {
            return [];
        }

        $currentAllowedLanguages = $audience?->allowed_languages;
        $settings = [
            'language_mode' => $given['language_mode'] ?? $audience?->language_mode->value ?? SubscribeFormFieldMode::Hidden->value,
            'allowed_languages' => array_values($given['allowed_languages'] ?? $currentAllowedLanguages ?? []),
            'default_language' => array_key_exists('default_language', $given)
                ? $given['default_language']
                : $audience?->default_language?->value,
        ];
        $languageIsVisible = $settings['language_mode'] !== SubscribeFormFieldMode::Hidden->value;

        Validator::make($settings, [
            'allowed_languages' => [$languageIsVisible ? 'required' : 'present', 'array', ...($languageIsVisible ? ['min:1'] : [])],
            'default_language' => [$languageIsVisible ? 'required' : 'nullable', Rule::in($settings['allowed_languages'])],
        ], [
            'allowed_languages.required' => 'Allow at least one language when the language field is optional or required.',
            'allowed_languages.min' => 'Allow at least one language when the language field is optional or required.',
            'default_language.required' => 'Choose a default_language from allowed_languages when the language field is optional or required.',
            'default_language.in' => 'The default_language must be one of allowed_languages.',
        ])->validate();

        return $settings;
    }
}
