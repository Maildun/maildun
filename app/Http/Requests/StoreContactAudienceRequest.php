<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreContactAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact && Gate::allows('update', $contact);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return [
            'audience_uuid' => [
                'required',
                'string',
                Rule::exists(Audience::class, 'uuid')->where('team_id', $team->id),
            ],
            'consent_confirmed' => ['required', 'accepted'],
        ];
    }
}
