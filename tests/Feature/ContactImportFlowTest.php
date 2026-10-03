<?php

use App\Enums\ContactImportMergeStrategy;
use App\Enums\ContactImportStatus;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Jobs\ProcessContactImport;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EmailAddressHealth;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    fakeMailDomains();
});

function draftImport(Team $team, string $csv, array $attributes = []): ContactImport
{
    $contactImport = ContactImport::factory()->for($team)->create([
        'status' => ContactImportStatus::Draft,
        'headers' => str_getcsv(strtok($csv, "\n"), $attributes['delimiter'] ?? ',', '"', ''),
        ...$attributes,
    ]);
    Storage::disk('local')->put($contactImport->path, $csv);

    return $contactImport;
}

function runImport(ContactImport $contactImport): ContactImport
{
    ProcessContactImport::dispatchSync($contactImport->id);

    return $contactImport->refresh();
}

test('uploads detect the delimiter and suggest a mapping from header names', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    AudienceAttribute::factory()->for($audience)->create(['name' => 'Plan', 'key' => 'plan']);

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $team), [
            'file' => UploadedFile::fake()->createWithContent(
                'contacts.csv',
                "E-mail;Surname;Plan;Notes\nada@example.com;Lovelace;pro;x\n\nalan@example.com;Turing;free;y\n",
            ),
            'audience' => $audience->uuid,
        ])
        ->assertSessionHasNoErrors();

    $contactImport = $team->contactImports()->sole();

    expect($contactImport->delimiter)->toBe(';')
        ->and($contactImport->headers)->toBe(['E-mail', 'Surname', 'Plan', 'Notes'])
        ->and($contactImport->column_map)->toBe(['email', 'last_name', 'attribute:plan', null])
        ->and($contactImport->total_rows)->toBe(2)
        ->and($contactImport->sample_rows)->toHaveCount(2);
});

test('uploads without data rows are rejected', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $user->currentTeam), [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', "email\n"),
        ])
        ->assertSessionHasErrors(['file' => 'The CSV has a header row but no contacts.']);
});

test('starting an import saves the mapping and queues it', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contactImport = draftImport($team, "Mail,Name\nada@example.com,Ada\n");

    $this->actingAs($user)
        ->post(route('contacts.imports.start', [$team, $contactImport]), [
            'column_map' => ['email', 'first_name'],
            'merge_strategy' => 'fill_blanks',
            'tags' => ['Webinar'],
        ])
        ->assertRedirect(route('contacts.imports.show', [$team, $contactImport]));

    $contactImport->refresh();

    expect($contactImport->status)->toBe(ContactImportStatus::Pending)
        ->and($contactImport->column_map)->toBe(['email', 'first_name'])
        ->and($contactImport->merge_strategy)->toBe(ContactImportMergeStrategy::FillBlanks)
        ->and($contactImport->tag_names)->toBe(['Webinar']);
    Queue::assertPushed(ProcessContactImport::class, fn (ProcessContactImport $job): bool => $job->contactImportId === $contactImport->id);
});

test('starting an import requires an email column and unique fields', function (array $columnMap, string $message) {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = draftImport($user->currentTeam, "a,b\nada@example.com,Ada\n");

    $this->actingAs($user)
        ->post(route('contacts.imports.start', [$user->currentTeam, $contactImport]), [
            'column_map' => $columnMap,
            'merge_strategy' => 'skip',
        ])
        ->assertSessionHasErrors(['column_map' => $message]);

    expect($contactImport->refresh()->status)->toBe(ContactImportStatus::Draft);
    Queue::assertNothingPushed();
})->with([
    'no email' => [['first_name', null], 'Choose which column holds the email address.'],
    'duplicate field' => [['email', 'email'], 'Each field can only be mapped to one column.'],
]);

test('audience imports require consent confirmation before starting', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $contactImport = draftImport($user->currentTeam, "email\nada@example.com\n", ['audience_id' => $audience->id]);

    $this->actingAs($user)
        ->post(route('contacts.imports.start', [$user->currentTeam, $contactImport]), [
            'column_map' => ['email'],
            'merge_strategy' => 'skip',
        ])
        ->assertSessionHasErrors(['consent_confirmed' => 'Confirm that these contacts agreed to receive your emails.']);

    Queue::assertNothingPushed();
});

test('members without contact permission cannot start an import', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $contactImport = draftImport($team, "email\nada@example.com\n");

    $this->actingAs($viewer)
        ->post(route('contacts.imports.start', [$team, $contactImport]), [
            'column_map' => ['email'],
            'merge_strategy' => 'skip',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('imports from another workspace are not found', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $otherImport = draftImport(Team::factory()->create(), "email\nada@example.com\n");

    $this->actingAs($user)
        ->get(route('contacts.imports.show', [$user->currentTeam, $otherImport]))
        ->assertNotFound();
});

test('the import page shows mapping options for a draft', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = draftImport($user->currentTeam, "email,first\nada@example.com,Ada\n", [
        'column_map' => ['email', 'first_name'],
        'sample_rows' => [['ada@example.com', 'Ada']],
    ]);

    $this->actingAs($user)
        ->get(route('contacts.imports.show', [$user->currentTeam, $contactImport]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/imports/show')
            ->where('contactImport.status', 'draft')
            ->where('mapping.headers', ['email', 'first'])
            ->where('mapping.columnMap', ['email', 'first_name'])
            ->where('canManage', true));
});

test('the import page opens for an audience import', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create(['name' => 'Product newsletter']);
    $contactImport = ContactImport::factory()->for($user->currentTeam)->for($audience)->create([
        'status' => ContactImportStatus::Completed,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.imports.show', [$user->currentTeam, $contactImport]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactImport.audience.name', 'Product newsletter')
            ->where('canManage', true));
});

test('mapped columns, delimiters and tags are imported', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $contactImport = draftImport($team, "Mail;Given;Labels\nada@example.com;Ada;vip|beta\n", [
        'delimiter' => ';',
        'column_map' => ['email', 'first_name', 'tags'],
        'tag_names' => ['Webinar'],
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);
    $contact = $team->contacts()->where('email', 'ada@example.com')->sole();

    expect($contactImport->status)->toBe(ContactImportStatus::Completed)
        ->and($contactImport->imported_contacts)->toBe(1)
        ->and($contact->first_name)->toBe('Ada')
        ->and($contact->tags()->pluck('name')->sort()->values()->all())->toBe(['Webinar', 'beta', 'vip']);
});

test('fill blanks completes empty fields without overwriting existing values', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $existing = Contact::factory()->for($team)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Augusta',
        'last_name' => null,
    ]);
    $unchanged = Contact::factory()->for($team)->create([
        'email' => 'grace@example.com',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);
    $contactImport = draftImport($team, "email,first_name,last_name\nada@example.com,Ada,Lovelace\ngrace@example.com,G,H\n", [
        'column_map' => ['email', 'first_name', 'last_name'],
        'merge_strategy' => ContactImportMergeStrategy::FillBlanks,
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);

    expect($existing->refresh()->first_name)->toBe('Augusta')
        ->and($existing->last_name)->toBe('Lovelace')
        ->and($unchanged->refresh()->only(['first_name', 'last_name']))->toBe(['first_name' => 'Grace', 'last_name' => 'Hopper'])
        ->and($contactImport->updated_contacts)->toBe(1)
        ->and($contactImport->duplicate_rows)->toBe(1);
});

test('skip leaves existing contacts and their tags unchanged', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $existing = Contact::factory()->for($team)->create(['email' => 'ada@example.com', 'last_name' => null]);
    $contactImport = draftImport($team, "email,last_name\nada@example.com,Lovelace\n", [
        'column_map' => ['email', 'last_name'],
        'tag_names' => ['Webinar'],
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);

    expect($existing->refresh()->last_name)->toBeNull()
        ->and($existing->tags()->count())->toBe(0)
        ->and($contactImport->duplicate_rows)->toBe(1);
});

test('audience imports keep unsubscribed people unsubscribed unless resubscribing is enabled', function (bool $resubscribe, SubscriberStatus $status) {
    Storage::fake('local');
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $subscriber = Subscriber::factory()->for($audience)->for($contact)->unsubscribed()->create(['email' => $contact->email]);
    $contactImport = draftImport($team, "email\nada@example.com\n", [
        'audience_id' => $audience->id,
        'column_map' => ['email'],
        'resubscribe_unsubscribed' => $resubscribe,
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);

    expect($subscriber->refresh()->status)->toBe($status)
        ->and($contactImport->skipped_unsubscribed)->toBe($resubscribe ? 0 : 1)
        ->and($contactImport->imported_subscribers)->toBe($resubscribe ? 1 : 0);
})->with([
    'default' => [false, SubscriberStatus::Unsubscribed],
    'resubscribe' => [true, SubscriberStatus::Subscribed],
]);

test('audience attributes are imported and invalid values are reported with their row', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    AudienceAttribute::factory()->for($audience)->number()->create(['name' => 'Seats', 'key' => 'seats']);
    $contactImport = draftImport($team, "email,seats\nada@example.com,5\n\nalan@example.com,many\n", [
        'audience_id' => $audience->id,
        'column_map' => ['email', 'attribute:seats'],
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);

    expect($audience->subscribers()->where('email', 'ada@example.com')->value('attribute_values'))->toBe(['seats' => '5'])
        ->and($contactImport->failed_rows)->toBe(1)
        ->and($contactImport->rowErrors())->toBe([
            ['row' => 4, 'email' => 'alan@example.com', 'message' => 'Seats must be a number.'],
        ]);
});

test('failed imports can be retried from their checkpoint', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = draftImport($user->currentTeam, "email\nada@example.com\n", [
        'status' => ContactImportStatus::Failed,
        'failure_message' => 'The worker stopped.',
        'file_offset' => 6,
    ]);

    $this->actingAs($user)
        ->post(route('contacts.imports.retry', [$user->currentTeam, $contactImport]))
        ->assertRedirect();

    expect($contactImport->refresh()->status)->toBe(ContactImportStatus::Pending)
        ->and($contactImport->failure_message)->toBeNull()
        ->and($contactImport->file_offset)->toBe(6);
    Queue::assertPushed(ProcessContactImport::class);
});

test('cancelled imports stop processing and remove the uploaded file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = draftImport($user->currentTeam, "email\nada@example.com\n", [
        'column_map' => ['email'],
        'status' => ContactImportStatus::Processing,
    ]);

    $this->actingAs($user)
        ->post(route('contacts.imports.cancel', [$user->currentTeam, $contactImport]))
        ->assertRedirect();

    ProcessContactImport::dispatchSync($contactImport->id);

    expect($contactImport->refresh()->status)->toBe(ContactImportStatus::Cancelled)
        ->and($user->currentTeam->contacts()->count())->toBe(0);
    Storage::disk('local')->assertMissing($contactImport->path);
});

test('the import report downloads failed and flagged rows as a CSV', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = ContactImport::factory()->for($user->currentTeam)->create([
        'original_name' => 'leads.csv',
        'status' => ContactImportStatus::Completed,
        'errors' => [['row' => 3, 'email' => 'broken', 'message' => 'Invalid email address.']],
        'review_flags' => [['row' => 4, 'email' => 'ada@gmial.com', 'issue' => 'typo', 'action' => 'fixed', 'detail' => 'ada@gmail.com']],
    ]);

    $response = $this->actingAs($user)
        ->get(route('contacts.imports.report', [$user->currentTeam, $contactImport]))
        ->assertOk()
        ->assertDownload('leads-report.csv');

    expect($response->streamedContent())->toBe(
        "row,email,outcome,detail\n3,broken,failed,\"Invalid email address.\"\n4,ada@gmial.com,fixed,\"Possible typo ada@gmail.com\"\n",
    );
});

test('typos are fixed only when the typed domain cannot receive mail', function (?bool $typedDomainAcceptsMail, string $importedEmail, string $action) {
    Storage::fake('local');
    fakeMailDomains(['gmial.com' => $typedDomainAcceptsMail]);
    $team = Team::factory()->create();
    $contactImport = draftImport($team, "email\nada@gmial.com\n", [
        'column_map' => ['email'],
        'status' => ContactImportStatus::Pending,
    ]);

    $contactImport = runImport($contactImport);

    expect($team->contacts()->pluck('email')->all())->toBe([$importedEmail])
        ->and($contactImport->review_flags)->toBe([
            ['row' => 2, 'email' => 'ada@gmial.com', 'issue' => 'typo', 'action' => $action, 'detail' => 'ada@gmail.com'],
        ])
        ->and($contactImport->flagged_rows)->toBe(1);
})->with([
    'no mail server' => [false, 'ada@gmail.com', 'fixed'],
    'dns unavailable' => [null, 'ada@gmial.com', 'imported'],
]);

test('a typo-like domain that receives mail is not flagged', function () {
    Storage::fake('local');
    fakeMailDomains(['hotmail.fr' => true]);
    $team = Team::factory()->create();
    $contactImport = runImport(draftImport($team, "email\nada@hotmail.fr\n", [
        'column_map' => ['email'],
        'status' => ContactImportStatus::Pending,
    ]));

    expect($contactImport->review_flags)->toBeNull()
        ->and($team->contacts()->value('email'))->toBe('ada@hotmail.fr');
});

test('disposable and undeliverable addresses are skipped by default and role addresses are flagged', function () {
    Storage::fake('local');
    fakeMailDomains(['acme.com' => true, 'nowhere.example' => false]);
    $team = Team::factory()->create();
    $contactImport = runImport(draftImport($team, "email\nada@mailinator.com\nbob@nowhere.example\ninfo@acme.com\n", [
        'column_map' => ['email'],
        'status' => ContactImportStatus::Pending,
    ]));

    expect($team->contacts()->pluck('email')->all())->toBe(['info@acme.com'])
        ->and($contactImport->skipped_rows)->toBe(2)
        ->and($contactImport->flagged_rows)->toBe(1)
        ->and($contactImport->review_counts)->toBe(['disposable' => 1, 'undeliverable' => 1, 'role_address' => 1])
        ->and(collect($contactImport->review_flags)->pluck('action')->all())->toBe(['skipped', 'skipped', 'imported']);
});

test('chosen check actions override the defaults', function () {
    Storage::fake('local');
    fakeMailDomains(['acme.com' => true]);
    $team = Team::factory()->create();
    $contactImport = runImport(draftImport($team, "email\nada@mailinator.com\ninfo@acme.com\n", [
        'column_map' => ['email'],
        'review_options' => ['disposable' => 'import', 'role_address' => 'skip'],
        'status' => ContactImportStatus::Pending,
    ]));

    expect($team->contacts()->pluck('email')->all())->toBe(['ada@mailinator.com'])
        ->and($contactImport->skipped_rows)->toBe(1);
});

test('rows repeated in the file are flagged and merged into the first row', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $contactImport = runImport(draftImport($team, "email,first_name,last_name\nada@acme.com,Ada,\nADA@acme.com,,Lovelace\n", [
        'column_map' => ['email', 'first_name', 'last_name'],
        'merge_strategy' => ContactImportMergeStrategy::FillBlanks,
        'status' => ContactImportStatus::Pending,
    ]));

    expect($team->contacts()->sole()->only(['first_name', 'last_name']))->toBe(['first_name' => 'Ada', 'last_name' => 'Lovelace'])
        ->and($contactImport->review_flags)->toBe([
            ['row' => 3, 'email' => 'ada@acme.com', 'issue' => 'duplicate_in_file', 'action' => 'imported', 'detail' => '2'],
        ])
        ->and(DB::table('contact_import_emails')->where('contact_import_id', $contactImport->id)->count())->toBe(0);
});

test('previously suppressed addresses are flagged', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'ada@acme.com']);
    $contactImport = runImport(draftImport($team, "email\nada@acme.com\n", [
        'column_map' => ['email'],
        'status' => ContactImportStatus::Pending,
    ]));

    expect($contactImport->review_counts)->toBe(['suppressed' => 1])
        ->and($contactImport->imported_contacts)->toBe(1);
});

test('starting an import rejects a check action the check does not offer', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $user = User::factory()->create();
    $contactImport = draftImport($user->currentTeam, "email\nada@example.com\n");

    $this->actingAs($user)
        ->post(route('contacts.imports.start', [$user->currentTeam, $contactImport]), [
            'column_map' => ['email'],
            'merge_strategy' => 'skip',
            'review_options' => ['disposable' => 'fix'],
        ])
        ->assertSessionHasErrors('review_options.disposable');

    Queue::assertNothingPushed();
});
