<?php

use App\Actions\Audiences\AddContactToAudiences;
use App\Enums\ContactImportStatus;
use App\Enums\TeamRole;
use App\Events\SubscriberLifecycleOccurred;
use App\Jobs\ProcessContactImport;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;
use App\Services\ContactImportCsv;
use App\Services\ImportEmailReview;
use App\Services\ManageContact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fakeMailDomains();
});

test('contact CSV uploads are stored privately as a draft awaiting column mapping', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $team), [
            'file' => UploadedFile::fake()->createWithContent(
                'contacts.csv',
                "email,first_name,last_name\ntaylor@example.com,Taylor,Otwell\n",
            ),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contacts.imports.show', [$team, $team->contactImports()->sole()]));

    $contactImport = $team->contactImports()->sole();

    expect($contactImport->original_name)->toBe('contacts.csv')
        ->and($contactImport->status)->toBe(ContactImportStatus::Draft)
        ->and($contactImport->consent_ip)->toBe('127.0.0.1')
        ->and($contactImport->audience_id)->toBeNull()
        ->and($contactImport->total_rows)->toBe(1)
        ->and($contactImport->column_map)->toBe(['email', 'first_name', 'last_name']);
    Storage::disk('local')->assertExists($contactImport->path);
    Queue::assertNothingPushed();
});

test('audience CSV uploads are scoped to the audience', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $team), [
            'file' => UploadedFile::fake()->createWithContent(
                'audience.csv',
                "email\ntaylor@example.com\n",
            ),
            'audience' => $audience->uuid,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($team->contactImports()->sole()->audience_id)->toBe($audience->id);
});

test('members without contact management permission cannot queue imports', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);

    $this->actingAs($member)
        ->post(route('contacts.imports.store', $team), [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', "email\nnope@example.com\n"),
        ])
        ->assertForbidden();

    expect($team->contactImports()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('audience imports cannot target an audience from another workspace', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');

    $user = User::factory()->create();
    $otherAudience = Audience::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $user->currentTeam), [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', "email\nnope@example.com\n"),
            'audience' => $otherAudience->uuid,
        ])
        ->assertSessionHasErrors(['audience' => 'Choose an audience from this workspace.']);

    expect($user->currentTeam->contactImports()->count())->toBe(0);
});

test('contact imports create valid contacts and count duplicate and invalid rows', function () {
    Storage::fake('local');

    $team = Team::factory()->create();
    Contact::factory()->for($team)->create([
        'email' => 'existing@example.com',
        'first_name' => 'Existing',
    ]);
    $contactImport = ContactImport::factory()->for($team)->create();
    Storage::disk('local')->put($contactImport->path, implode("\n", [
        'Email,First Name,Last Name',
        'NEW@example.com,New,Person',
        'existing@example.com,Replacement,Name',
        'not-an-email,Broken,Address',
    ]));

    (new ProcessContactImport($contactImport->id))->handle(
        app(ManageContact::class),
        app(AddContactToAudiences::class),
        app(ContactImportCsv::class),
        app(ImportEmailReview::class),
    );

    $contactImport->refresh();

    expect($team->contacts()->count())->toBe(2)
        ->and($team->contacts()->where('email', 'new@example.com')->value('first_name'))->toBe('New')
        ->and($team->contacts()->where('email', 'existing@example.com')->value('first_name'))->toBe('Existing')
        ->and($contactImport->status)->toBe(ContactImportStatus::Completed)
        ->and($contactImport->processed_rows)->toBe(3)
        ->and($contactImport->imported_contacts)->toBe(1)
        ->and($contactImport->imported_subscribers)->toBe(0)
        ->and($contactImport->duplicate_rows)->toBe(1)
        ->and($contactImport->failed_rows)->toBe(1)
        ->and($contactImport->errors)->toHaveCount(1);
    Storage::disk('local')->assertMissing($contactImport->path);
});

test('audience imports reuse contacts and skip duplicate memberships', function () {
    Storage::fake('local');

    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $existing = Contact::factory()->for($team)->create(['email' => 'existing@example.com']);
    $duplicate = Contact::factory()->for($team)->create(['email' => 'duplicate@example.com']);
    Subscriber::factory()->for($audience)->for($duplicate)->create(['email' => $duplicate->email]);
    $contactImport = ContactImport::factory()->for($team)->for($audience)->create();
    Storage::disk('local')->put($contactImport->path, implode("\n", [
        'email,first_name,last_name',
        'existing@example.com,Ignored,Profile',
        'duplicate@example.com,Ignored,Again',
        'new@example.com,New,Contact',
    ]));
    Event::fake([SubscriberLifecycleOccurred::class]);

    (new ProcessContactImport($contactImport->id))->handle(
        app(ManageContact::class),
        app(AddContactToAudiences::class),
        app(ContactImportCsv::class),
        app(ImportEmailReview::class),
    );

    $contactImport->refresh();

    expect($team->contacts()->count())->toBe(3)
        ->and($audience->subscribers()->count())->toBe(3)
        ->and($existing->fresh()->first_name)->not->toBe('Ignored')
        ->and($contactImport->imported_contacts)->toBe(1)
        ->and($contactImport->imported_subscribers)->toBe(2)
        ->and($contactImport->duplicate_rows)->toBe(1)
        ->and($contactImport->failed_rows)->toBe(0);
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 2);
});

test('imports fail with a useful error and keep the file for retry when the email header is missing', function () {
    Storage::fake('local');

    $team = Team::factory()->create();
    $contactImport = ContactImport::factory()->for($team)->create();
    Storage::disk('local')->put($contactImport->path, "first_name,last_name\nMissing,Email\n");
    $job = new ProcessContactImport($contactImport->id);

    try {
        $job->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class));
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    $contactImport->refresh();

    expect($contactImport->status)->toBe(ContactImportStatus::Failed)
        ->and($contactImport->failure_message)->toBe('The CSV must include an email column.')
        ->and($team->contacts()->count())->toBe(0);
    Storage::disk('local')->assertExists($contactImport->path);
});

test('imports reject files that are not CSV data', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.imports.store', $user->currentTeam), [
            'file' => UploadedFile::fake()->create('contacts.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

test('large contact imports checkpoint bounded chunks and retain the source until completed', function () {
    Queue::fake([ProcessContactImport::class]);
    Storage::fake('local');
    $team = Team::factory()->create();
    $import = ContactImport::factory()->for($team)->create();
    $rows = ['email'];

    for ($row = 1; $row <= 205; $row++) {
        $rows[] = 'contact'.$row.'@example.com';
    }

    Storage::disk('local')->put($import->path, implode("\n", $rows));
    $job = new ProcessContactImport($import->id);

    $job->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class));

    expect($import->refresh()->processed_rows)->toBe(200);
    expect($import->status)->toBe(ContactImportStatus::Processing);
    expect($import->file_offset)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($import->path);
    Queue::assertPushed(ProcessContactImport::class, 1);

    Queue::pushed(ProcessContactImport::class)->sole()->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class));
    $job->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class));
    $job->failed(new RuntimeException('A duplicate old worker failed.'));

    expect($import->refresh()->status)->toBe(ContactImportStatus::Completed);
    expect($import->processed_rows)->toBe(205);
    expect($import->imported_contacts)->toBe(205);
    expect($import->duplicate_rows)->toBe(0);
    expect($team->contacts()->count())->toBe(205);
    Storage::disk('local')->assertMissing($import->path);
});

test('the sync queue completes imports larger than one chunk', function () {
    config()->set('queue.default', 'sync');
    Storage::fake('local');
    $team = Team::factory()->create();
    $import = ContactImport::factory()->for($team)->create();
    $rows = ['email'];

    for ($row = 1; $row <= 205; $row++) {
        $rows[] = 'sync'.$row.'@example.com';
    }

    Storage::disk('local')->put($import->path, implode("\n", $rows));
    ProcessContactImport::dispatchSync($import->id);

    expect($import->refresh()->status)->toBe(ContactImportStatus::Completed);
    expect($import->processed_rows)->toBe(205);
    expect($import->imported_contacts)->toBe(205);
    expect($import->duplicate_rows)->toBe(0);
    Storage::disk('local')->assertMissing($import->path);
});

test('an interrupted import retries the rolled back row without losing progress or its input', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $import = ContactImport::factory()->for($team)->create();
    Storage::disk('local')->put($import->path, "email\nnew@example.com\n");
    $shouldInterrupt = true;
    Contact::created(function () use (&$shouldInterrupt): void {
        if ($shouldInterrupt) {
            $shouldInterrupt = false;
            throw new RuntimeException('Import interrupted.');
        }
    });
    $job = new ProcessContactImport($import->id);

    expect(fn () => $job->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class)))
        ->toThrow(RuntimeException::class, 'Import interrupted.');

    expect($team->contacts()->count())->toBe(0);
    expect($import->refresh()->file_offset)->toBe(0);
    Storage::disk('local')->assertExists($import->path);

    $job->handle(app(ManageContact::class), app(AddContactToAudiences::class), app(ContactImportCsv::class), app(ImportEmailReview::class));

    expect($import->refresh()->status)->toBe(ContactImportStatus::Completed);
    expect($import->imported_contacts)->toBe(1);
    expect($import->duplicate_rows)->toBe(0);
});
