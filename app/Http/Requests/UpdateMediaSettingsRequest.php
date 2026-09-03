<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMediaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateSettings', [Media::class, $this->route('current_team')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'convert_uploads_to_webp' => ['required', 'boolean'],
        ];
    }
}
