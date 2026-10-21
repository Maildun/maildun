<?php

namespace App\Http\Requests;

use App\Actions\Audiences\ValidateSegmentRules;
use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Models\Audience;
use App\Models\Segment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'rules.*.field' => 'rule field',
            'rules.*.operator' => 'rule operator',
            'rules.*.value' => 'rule value',
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(ValidateSegmentRules $validateSegmentRules): array
    {
        return [function (Validator $validator) use ($validateSegmentRules): void {
            $audience = $this->route('audience');

            abort_unless($audience instanceof Audience, 404);

            foreach ($validateSegmentRules->handle($audience, $this->input('rules', [])) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }
}
