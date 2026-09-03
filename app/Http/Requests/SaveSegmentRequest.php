<?php

namespace App\Http\Requests;

use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Segment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class SaveSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $segment = $this->route('segment');

        return $segment instanceof Segment
            ? Gate::allows('update', $segment)
            : Gate::allows('create', [Segment::class, $this->route('audience')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'match_type' => ['required', Rule::enum(SegmentMatchType::class)],
            'rules' => ['required', 'array', 'min:1', 'max:10'],
            'rules.*.field' => ['required', Rule::enum(SegmentRuleField::class)],
            'rules.*.operator' => ['required', Rule::enum(SegmentRuleOperator::class)],
            'rules.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $audience = $this->route('audience');

            abort_unless($audience instanceof Audience, 404);

            foreach ($this->input('rules', []) as $index => $rule) {
                $field = SegmentRuleField::tryFrom($rule['field'] ?? '');
                $operator = SegmentRuleOperator::tryFrom($rule['operator'] ?? '');
                $value = $rule['value'] ?? null;

                if (! $field || ! $operator || ! in_array($operator, $field->operators(), true)) {
                    $validator->errors()->add("rules.{$index}.operator", 'The selected operator is not valid for this field.');

                    continue;
                }

                if ($field === SegmentRuleField::Status && ! SubscriberStatus::tryFrom((string) $value)) {
                    $validator->errors()->add("rules.{$index}.value", 'The selected subscriber status is invalid.');
                }

                if ($field === SegmentRuleField::Source && ! SubscriberSource::tryFrom((string) $value)) {
                    $validator->errors()->add("rules.{$index}.value", 'The selected subscriber source is invalid.');
                }

                if ($field === SegmentRuleField::SubscribeForm && ! $audience->subscribeForms()->withTrashed()->where('uuid', $value)->exists()) {
                    $validator->errors()->add("rules.{$index}.value", 'The selected subscribe form is invalid.');
                }

                if ($field === SegmentRuleField::SubscribedAt) {
                    try {
                        Carbon::parse((string) $value);
                    } catch (Throwable) {
                        $validator->errors()->add("rules.{$index}.value", 'The value must be a valid date.');
                    }
                }
            }
        }];
    }
}
