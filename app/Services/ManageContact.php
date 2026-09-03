<?php

namespace App\Services;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ManageContact
{
    public function __construct(private ResolveContactCompany $resolveCompany) {}

    /**
     * @param  array{email: string, first_name?: string|null, last_name?: string|null, company_id?: int|null, company_assignment_mode?: ContactCompanyAssignmentMode|string}  $attributes
     */
    public function findOrCreate(Team $team, array $attributes): Contact
    {
        $contact = $team->contacts()->firstOrCreate(
            ['email' => $attributes['email']],
            [
                ...Arr::only($attributes, ['first_name', 'last_name', 'company_id']),
                'company_assignment_mode' => $attributes['company_assignment_mode'] ?? ContactCompanyAssignmentMode::Automatic,
            ],
        );

        if (! $contact->wasRecentlyCreated) {
            return $contact;
        }

        $this->resolveCompany->handle($contact);
        $contact->refresh();

        return $contact;
    }

    /** @param array{email?: string, first_name?: string|null, last_name?: string|null, company_id?: int|null, company_assignment_mode?: ContactCompanyAssignmentMode|string} $attributes */
    public function update(Contact $contact, array $attributes): Contact
    {
        $contact->update(Arr::only($attributes, [
            'email',
            'first_name',
            'last_name',
            'company_id',
            'company_assignment_mode',
        ]));

        $this->resolveCompany->handle($contact);
        $this->syncSubscriberCopies($contact);

        return $contact->fresh() ?? $contact;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public function syncTags(Team $team, Contact $contact, array $names): array
    {
        $before = $contact->tags()->pluck('uuid')->all();

        $tagIds = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): int => $team->tags()->firstOrCreate(['name' => $name])->id);

        $contact->tags()->sync($tagIds);

        return array_values(
            $contact->tags()
                ->whereNotIn('uuid', $before)
                ->pluck('uuid')
                ->all(),
        );
    }

    private function syncSubscriberCopies(Contact $contact): void
    {
        $contact->subscribers()->update([
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
        ]);
    }
}
