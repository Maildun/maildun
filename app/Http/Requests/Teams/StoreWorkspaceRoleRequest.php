<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\WorkspaceRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkspaceRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_column(TeamPermission::cases(), 'value'))],
        ];
    }

    /**
     * Validate the stable role name within the selected workspace.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('name')) {
                return;
            }

            $team = $this->route('team');

            if (! $team instanceof Team) {
                return;
            }

            $name = WorkspaceRole::nameFromLabel($this->string('name')->toString());

            if ($name === '') {
                $validator->errors()->add('name', __('Enter a role name that contains letters or numbers.'));

                return;
            }

            if (TeamRole::tryFrom($name) !== null) {
                $validator->errors()->add('name', __('Owner, Admin, and Member are reserved workspace roles.'));

                return;
            }

            if ($team->workspaceRoles()->where('name', $name)->exists()) {
                $validator->errors()->add('name', __('A role with this name already exists in this workspace.'));
            }
        }];
    }
}
