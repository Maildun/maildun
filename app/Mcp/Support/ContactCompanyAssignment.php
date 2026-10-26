<?php

namespace App\Mcp\Support;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;

/**
 * The company inputs shared by create-contact and update-contact. They follow
 * the web contact form: a manual assignment pins a company (or no company) so
 * domain matching never overrides it, and automatic hands the contact back to
 * domain matching.
 */
class ContactCompanyAssignment
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(Team $team): array
    {
        return [
            'company_assignment_mode' => ['sometimes', Rule::enum(ContactCompanyAssignmentMode::class)],
            'company_uuid' => [
                'sometimes',
                'prohibited_if:company_assignment_mode,automatic',
                'nullable',
                'uuid',
                Rule::exists(Company::class, 'uuid')->where('team_id', $team->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_uuid.exists' => 'The selected company was not found in this workspace.',
            'company_uuid.prohibited_if' => 'Leave company_uuid out when company_assignment_mode is automatic; the company then follows the email domain.',
        ];
    }

    /**
     * The contact attributes to write, or an empty array when no company input was given.
     *
     * @param  array<string, mixed>  $validated
     * @return array{company_assignment_mode?: ContactCompanyAssignmentMode, company_id?: int|null}
     */
    public function attributes(array $validated, Team $team, ?Contact $contact = null): array
    {
        $hasCompany = array_key_exists('company_uuid', $validated);
        $mode = isset($validated['company_assignment_mode'])
            ? ContactCompanyAssignmentMode::from($validated['company_assignment_mode'])
            : ($hasCompany ? ContactCompanyAssignmentMode::Manual : null);

        if ($mode === null) {
            return [];
        }

        if ($mode === ContactCompanyAssignmentMode::Automatic) {
            return ['company_assignment_mode' => $mode, 'company_id' => null];
        }

        $companyId = $hasCompany
            ? ($validated['company_uuid'] === null ? null : $team->companies()->where('uuid', $validated['company_uuid'])->value('id'))
            : $contact?->company_id;

        return ['company_assignment_mode' => $mode, 'company_id' => $companyId];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company_assignment_mode' => $schema->string()
                ->description('automatic lets the email domain choose the company. manual keeps the company you set, even when no company. Defaults to manual when company_uuid is given.')
                ->enum(ContactCompanyAssignmentMode::class),
            'company_uuid' => $schema->string()
                ->description('Company UUID from list-companies to pin, or null to pin no company. Sets company_assignment_mode to manual.')
                ->format('uuid')
                ->nullable(),
        ];
    }
}
