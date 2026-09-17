<?php

namespace App\Http\Requests;

use App\Enums\EmailEditor;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Team;
use App\Rules\AuthorizedSenderAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmailRequest extends FormRequest
{
    public const string AUDIENCE_DEFAULT_SENDER = 'audience-default';

    public const string CURRENT_SENDER = 'current-sender';

    protected function prepareForValidation(): void
    {
        $queryString = $this->input('query_string');

        if (is_string($queryString)) {
            $normalized = ltrim(trim($queryString), '?&');
            $this->merge(['query_string' => $normalized === '' ? null : $normalized]);
        }
    }

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('email'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $email = $this->route('email');
        $team = $this->route('current_team');

        abort_unless($email instanceof Email, 404);
        abort_unless($team instanceof Team, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'sender_uuid' => [
                'sometimes',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($email, $team): void {
                    if ($value === self::AUDIENCE_DEFAULT_SENDER) {
                        return;
                    }

                    if ($value === self::CURRENT_SENDER
                        && ($email->from_name !== null || $email->from_address !== null || $email->reply_to !== null)) {
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
            // The block editor renders its document to HTML client-side, so
            // every editor ends up submitting the markup that gets sent.
            'html' => ['required', 'string', 'max:2000000'],
            // A source-based draft may be saved before its body is written;
            // StartEmailSend refuses to queue it while the source is blank.
            'source' => ['nullable', 'string', 'max:2000000'],
            'plain_text' => ['nullable', 'string', 'max:2000000'],
            'query_string' => ['nullable', 'string', 'max:2048', 'regex:/^[^\s?#]+$/'],
            'track_clicks' => ['sometimes', 'boolean'],
            'track_opens' => ['sometimes', 'boolean'],
            'design' => ['nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'audience' => [
                'nullable',
                'string',
                Rule::exists(Audience::class, 'uuid')->where('team_id', $email->team_id),
            ],
            'segment' => ['nullable', 'string'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateSegmentBelongsToAudience($validator);
            $this->validateDesignPresentForBuilder($validator);
        }];
    }

    /**
     * A segment only narrows the audience it was built for, so one without the
     * other — or from a different audience — is meaningless.
     */
    protected function validateSegmentBelongsToAudience(Validator $validator): void
    {
        $segmentUuid = $this->input('segment');

        if (blank($segmentUuid)) {
            return;
        }

        $audienceUuid = $this->input('audience');

        if (blank($audienceUuid)) {
            $validator->errors()->add('segment', __('Choose an audience before choosing a segment.'));

            return;
        }

        $belongs = Segment::query()
            ->where('uuid', $segmentUuid)
            ->whereHas('audience', fn ($query) => $query->where('uuid', $audienceUuid))
            ->exists();

        if (! $belongs) {
            $validator->errors()->add('segment', __('The selected segment is not part of that audience.'));
        }
    }

    /**
     * The block editor is the only editor that stores a document, and it is
     * useless without one.
     */
    protected function validateDesignPresentForBuilder(Validator $validator): void
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        if ($team->email_editor !== EmailEditor::Builder) {
            return;
        }

        if (blank($this->input('design'))) {
            $validator->errors()->add('design', __('The block layout is missing.'));
        }
    }
}
