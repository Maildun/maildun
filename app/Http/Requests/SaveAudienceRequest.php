<?php

namespace App\Http\Requests;

use App\Enums\SubscribeFormFieldMode;
use App\Enums\TransactionalEmailStatus;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Rules\AuthorizedSenderAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveAudienceRequest extends FormRequest
{
    public const string WORKSPACE_DEFAULT_SENDER = 'workspace-default';

    protected function prepareForValidation(): void
    {
        $this->merge($this->blankToNull([
            'description',
            'from_name',
            'from_address',
            'reply_to',
            'notification_email',
            'subscribed_url',
            'already_subscribed_url',
            'unsubscribed_url',
            'double_opt_in_email_uuid',
        ]));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return [
            'name' => $this->isMethod('post')
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'first_name_mode' => ['sometimes', Rule::enum(SubscribeFormFieldMode::class)],
            'last_name_mode' => ['sometimes', Rule::enum(SubscribeFormFieldMode::class)],
            'double_opt_in' => ['sometimes', 'boolean'],
            'double_opt_in_email_uuid' => [
                Rule::requiredIf(fn (): bool => $this->boolean('double_opt_in')),
                'nullable',
                'string',
                Rule::exists(TransactionalEmail::class, 'uuid')->where(
                    fn ($query) => $query
                        ->where('team_id', $team->id)
                        ->where('status', TransactionalEmailStatus::Published->value),
                ),
            ],
            'sender_uuid' => [
                'sometimes',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($team): void {
                    if ($value === self::WORKSPACE_DEFAULT_SENDER) {
                        return;
                    }

                    $sender = is_string($value)
                        ? $team->verifiedSenders()
                            ->where('uuid', $value)
                            ->first()
                        : null;

                    if ($sender === null) {
                        $fail(__('Select a verified sender from this workspace.'));

                        return;
                    }

                    (new AuthorizedSenderAddress($team))->validate($attribute, $sender->email, $fail);
                },
            ],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'email', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['nullable', 'string', 'email', 'max:255'],
            'notification_email' => ['nullable', 'string', 'email', 'max:255'],
            'subscribed_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'already_subscribed_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'unsubscribed_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function blankToNull(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            $values[$key] = is_string($value) && Str::of($value)->trim()->isEmpty()
                ? null
                : $value;
        }

        return $values;
    }
}
