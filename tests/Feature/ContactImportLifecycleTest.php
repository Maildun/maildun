<?php

use App\Enums\ContactImportStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\ContactImport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

function storedContactImport(Team $team, ContactImportStatus $status, array $attributes = []): ContactImport
{
    $contactImport = ContactImport::factory()->for($team)->create(['status' => $status, ...$attributes]);
    Storage::disk('local')->put($contactImport->path, "email\nada@example.com\n");

    return $contactImport;
}

test('import history lists only this workspace imports, newest first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $this->travel(-1)->days();
    $older = storedContactImport($team, ContactImportStatus::Completed, ['original_name' => 'older.csv']);
    $this->travelBack();
    $newer = storedContactImport($team, ContactImportStatus::Draft, ['original_name' => 'newer.csv']);
    storedContactImport(Team::factory()->create(), ContactImportStatus::Completed, ['original_name' => 'foreign.csv']);

    $this->actingAs($user)
        ->get(route('contacts.imports.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/imports/index')
            ->has('imports.data', 2)
            ->where('imports.data.0.uuid', $newer->uuid)
            ->where('imports.data.1.uuid', $older->uuid)
            ->where('canImport', true));
});

test('the audience page import menu opens the upload form with that audience selected', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create(['name' => 'Newsletter']);

    $this->actingAs($user)
        ->get(route('contacts.imports.create', [$user->currentTeam, 'audience' => $audience->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/imports/create')
            ->where('selectedAudience', $audience->uuid)
            ->where('audiences.0.name', 'Newsletter')
            ->where('canImportContacts', true));
});

test('the upload form ignores an audience from another workspace', function () {
    $user = User::factory()->create();
    $otherAudience = Audience::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('contacts.imports.create', [$user->currentTeam, 'audience' => $otherAudience->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedAudience', null)
            ->has('audiences', 0));
});

test('a running import shows its progress on the import page', function () {
    $user = User::factory()->create();
    $contactImport = storedContactImport($user->currentTeam, ContactImportStatus::Processing, [
        'total_rows' => 10,
        'processed_rows' => 4,
        'imported_contacts' => 3,
        'duplicate_rows' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.imports.show', [$user->currentTeam, $contactImport]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactImport.status', 'processing')
            ->where('contactImport.total_rows', 10)
            ->where('contactImport.processed_rows', 4)
            ->where('contactImport.imported_contacts', 3)
            ->where('mapping', null)
            ->where('canRetry', false));
});

test('deleting a finished import removes it and its uploaded file', function (ContactImportStatus $status) {
    $user = User::factory()->create();
    $contactImport = storedContactImport($user->currentTeam, $status);

    $this->actingAs($user)
        ->delete(route('contacts.imports.destroy', [$user->currentTeam, $contactImport]))
        ->assertRedirect(route('contacts.imports.index', $user->currentTeam));

    $this->assertModelMissing($contactImport);
    Storage::disk('local')->assertMissing($contactImport->path);
})->with([
    'draft' => ContactImportStatus::Draft,
    'failed' => ContactImportStatus::Failed,
    'completed' => ContactImportStatus::Completed,
]);

test('a running import cannot be deleted', function () {
    $user = User::factory()->create();
    $contactImport = storedContactImport($user->currentTeam, ContactImportStatus::Processing);
    $this->actingAs($user)
        ->delete(route('contacts.imports.destroy', [$user->currentTeam, $contactImport]))
        ->assertConflict();

    $this->assertModelExists($contactImport);
    Storage::disk('local')->assertExists($contactImport->path);
});

test('members without contact permission cannot delete an import', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $contactImport = storedContactImport($team, ContactImportStatus::Completed);

    $this->actingAs($viewer)
        ->delete(route('contacts.imports.destroy', [$team, $contactImport]))
        ->assertForbidden();

    $this->assertModelExists($contactImport);
});

test('only a failed import that still has its file can be retried', function (ContactImportStatus $status, bool $fileKept) {
    $user = User::factory()->create();
    $contactImport = storedContactImport($user->currentTeam, $status);
    if (! $fileKept) {
        Storage::disk('local')->delete($contactImport->path);
    }

    $this->actingAs($user)
        ->post(route('contacts.imports.retry', [$user->currentTeam, $contactImport]))
        ->assertConflict();

    expect($contactImport->refresh()->status)->toBe($status);
})->with([
    'completed' => [ContactImportStatus::Completed, true],
    'failed after its file was pruned' => [ContactImportStatus::Failed, false],
]);
