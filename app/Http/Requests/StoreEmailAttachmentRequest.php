<?php

namespace App\Http\Requests;

use App\Enums\EmailStatus;
use App\Models\Email;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreEmailAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('email'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,gif,png,pdf,zip',
                'extensions:jpeg,jpg,gif,png,pdf,zip',
                'max:10240',
            ],
        ];
    }

    public function ensureDraftCanAccept(int $incomingCount): void
    {
        $email = $this->route('email');

        abort_unless($email instanceof Email && $email->status === EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));

        if ($email->attachments()->count() + $incomingCount > 10) {
            throw ValidationException::withMessages([
                'attachments' => __('A campaign may have up to 10 attachments.'),
            ]);
        }
    }
}
