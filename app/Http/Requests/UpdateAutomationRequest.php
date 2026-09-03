<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('automation'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'graph' => ['required', 'array'],
            'graph.nodes' => ['required', 'array', 'min:1'],
            'graph.nodes.*.id' => ['required', 'string', 'max:64'],
            'graph.nodes.*.type' => ['required', 'string', 'in:trigger,action,delay,condition'],
            'graph.nodes.*.position' => ['required', 'array'],
            'graph.nodes.*.position.x' => ['required', 'numeric'],
            'graph.nodes.*.position.y' => ['required', 'numeric'],
            'graph.nodes.*.data' => ['nullable', 'array'],
            'graph.edges' => ['present', 'array'],
            'graph.edges.*.id' => ['required', 'string', 'max:64'],
            'graph.edges.*.source' => ['required', 'string', 'max:64'],
            'graph.edges.*.target' => ['required', 'string', 'max:64'],
            'graph.edges.*.sourceHandle' => ['nullable', 'string', 'max:32'],
            'graph.edges.*.targetHandle' => ['nullable', 'string', 'max:32'],
        ];
    }
}
