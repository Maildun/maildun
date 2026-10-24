<?php

use App\Enums\ContactImportStatus;
use App\Models\ContactImport;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function storedImport(ContactImportStatus $status, array $attributes = []): ContactImport
{
    $contactImport = ContactImport::factory()->create(['status' => $status, ...$attributes]);
    Storage::disk('local')->put($contactImport->path, "email\nada@example.com\n");

    return $contactImport;
}

test('abandoned drafts are deleted with their uploaded file', function () {
    $this->travel(-8)->days();
    $abandoned = storedImport(ContactImportStatus::Draft);
    $this->travelBack();
    $recent = storedImport(ContactImportStatus::Draft);

    $this->artisan('contacts:prune-imports')->assertSuccessful();

    $this->assertModelMissing($abandoned);
    $this->assertModelExists($recent);
    Storage::disk('local')->assertMissing($abandoned->path);
    Storage::disk('local')->assertExists($recent->path);
});

test('failed imports nobody retried lose their file but keep their history', function () {
    $old = storedImport(ContactImportStatus::Failed, ['completed_at' => now()->subDays(31)]);
    $recent = storedImport(ContactImportStatus::Failed, ['completed_at' => now()->subDays(5)]);
    $running = storedImport(ContactImportStatus::Processing, ['completed_at' => null]);

    $this->artisan('contacts:prune-imports')->assertSuccessful();

    $this->assertModelExists($old);
    Storage::disk('local')->assertMissing($old->path);
    Storage::disk('local')->assertExists($recent->path);
    Storage::disk('local')->assertExists($running->path);
});
