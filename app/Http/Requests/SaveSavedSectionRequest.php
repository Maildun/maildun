<?php

namespace App\Http\Requests;

use App\Models\SavedSection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveSavedSectionRequest extends FormRequest
{
    /** Big enough for a header or footer with inline images, small enough to keep page props light. */
    private const int MAX_BLOCKS_BYTES = 200_000;

    public function authorize(): bool
    {
        $section = $this->route('savedSection');

        return $section instanceof SavedSection
            ? Gate::allows('update', $section)
            : Gate::allows('create', [SavedSection::class, $this->route('current_team')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $creating = ! $this->route('savedSection') instanceof SavedSection;

        return [
            'name' => ['required', 'string', 'max:80'],
            'blocks' => [
                $creating ? 'required' : 'prohibited',
                'array',
                'list',
                'min:1',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen((string) json_encode($value)) > self::MAX_BLOCKS_BYTES) {
                        $fail(__('This section is too large to save.'));
                    }
                },
            ],
            'blocks.*' => ['array'],
            'blocks.*.type' => ['required', 'string', 'max:50'],
        ];
    }
}
