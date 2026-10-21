<?php

namespace App\Mcp\Support;

use App\Models\Audience;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

class WorkspaceContext
{
    public function team(Request $request): Team
    {
        $validated = $request->validate([
            'workspace' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = $request->user();
        $query = $user instanceof User ? $user->teams() : Team::query();
        $team = $query->where('teams.slug', $validated['workspace'])->first();

        if (! $team instanceof Team) {
            throw ValidationException::withMessages([
                'workspace' => 'The selected workspace was not found.',
            ]);
        }

        return $team;
    }

    public function audience(Request $request, Team $team): Audience
    {
        $uuid = $this->scopedUuid($request, Audience::class, $team, 'audience');

        $audience = $team->audiences()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $audience);

        return $audience;
    }

    /**
     * Resolve the audience a nested resource is created in or filtered by,
     * from an `audience_uuid` argument rather than the record `uuid`.
     */
    public function audienceFrom(Request $request, Team $team, string $key = 'audience_uuid'): Audience
    {
        $uuid = $this->scopedUuid($request, Audience::class, $team, 'audience', key: $key);

        $audience = $team->audiences()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $audience);

        return $audience;
    }

    public function contact(Request $request, Team $team): Contact
    {
        $uuid = $this->scopedUuid($request, Contact::class, $team, 'contact');

        $contact = $team->contacts()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $contact);

        return $contact;
    }

    public function subscriber(Request $request, Team $team): Subscriber
    {
        $uuid = $this->audienceScopedUuid($request, Subscriber::class, $team, 'subscriber');

        $subscriber = $team->subscribers()->where('subscribers.uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $subscriber);

        return $subscriber;
    }

    public function segment(Request $request, Team $team): Segment
    {
        $uuid = $this->audienceScopedUuid($request, Segment::class, $team, 'segment');

        $segment = Segment::query()
            ->whereIn('audience_id', $team->audiences()->select('id'))
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize($request, 'view', $segment);

        return $segment;
    }

    public function campaign(Request $request, Team $team): Email
    {
        $uuid = $this->scopedUuid($request, Email::class, $team, 'campaign', softDeletes: true);

        $campaign = $team->emails()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $campaign);

        return $campaign;
    }

    public function transactionalEmail(Request $request, Team $team): TransactionalEmail
    {
        $uuid = $this->scopedUuid($request, TransactionalEmail::class, $team, 'transactional email', softDeletes: true);

        $email = $team->transactionalEmails()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $email);

        return $email;
    }

    public function automation(Request $request, Team $team): Automation
    {
        $uuid = $this->scopedUuid($request, Automation::class, $team, 'automation', softDeletes: true);

        $automation = $team->automations()->where('uuid', $uuid)->firstOrFail();
        $this->authorize($request, 'view', $automation);

        return $automation;
    }

    /**
     * @param  Model|class-string<Model>|array<int, Model|class-string<Model>>  $arguments
     */
    public function authorize(Request $request, string $ability, Model|string|array $arguments): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        Gate::forUser($user)->authorize($ability, $arguments);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function changes(array $validated, array $fields): array
    {
        $changes = Arr::only($validated, $fields);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => 'Provide at least one field to update.',
            ]);
        }

        return $changes;
    }

    /**
     * Put the whole block document back into validated input. The rules only
     * check its root, and validated() drops every nested key without a rule.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function withFullDesign(Request $request, array $validated): array
    {
        if (array_key_exists('design', $validated)) {
            $validated['design'] = $request->get('design');
        }

        return $validated;
    }

    /**
     * Trim names and other short free text the way the web forms' TrimStrings
     * middleware does, and store a blank value as null. Not for message bodies,
     * whose leading and trailing whitespace can be meaningful.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function trimStrings(array $values, array $fields): array
    {
        foreach ($fields as $field) {
            $value = $values[$field] ?? null;

            if (is_string($value)) {
                $trimmed = trim($value);
                $values[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function blankStringsToNull(array $values, array $fields): array
    {
        foreach ($fields as $field) {
            $value = $values[$field] ?? null;

            if (is_string($value) && Str::of($value)->trim()->isEmpty()) {
                $values[$field] = null;
            }
        }

        return $values;
    }

    /**
     * @param  class-string<Audience|Automation|Contact|Email|TransactionalEmail>  $model
     */
    private function scopedUuid(
        Request $request,
        string $model,
        Team $team,
        string $resource,
        bool $softDeletes = false,
        string $key = 'uuid',
    ): string {
        $rule = Rule::exists($model, 'uuid')->where('team_id', $team->id);

        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        $validated = $request->validate([
            $key => ['required', 'uuid', $rule],
        ], [
            "{$key}.exists" => "The selected {$resource} was not found in this workspace.",
        ]);

        return (string) $validated[$key];
    }

    /**
     * Validate the UUID of a record that belongs to the workspace through one
     * of its audiences rather than a team_id column of its own.
     *
     * @param  class-string<Segment|Subscriber>  $model
     */
    private function audienceScopedUuid(Request $request, string $model, Team $team, string $resource): string
    {
        $rule = Rule::exists($model, 'uuid')->where(
            fn (Builder $query) => $query->whereIn('audience_id', $team->audiences()->select('id')),
        );

        $validated = $request->validate([
            'uuid' => ['required', 'uuid', $rule],
        ], [
            'uuid.exists' => "The selected {$resource} was not found in this workspace.",
        ]);

        return (string) $validated['uuid'];
    }
}
