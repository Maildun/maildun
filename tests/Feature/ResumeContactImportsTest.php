<?php

use App\Enums\ContactImportStatus;
use App\Jobs\ProcessContactImport;
use App\Models\ContactImport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

test('imports that stopped moving are re-dispatched once per stall', function () {
    $this->travel(-20)->minutes();
    $stalled = collect([ContactImportStatus::Pending, ContactImportStatus::Processing])
        ->map(fn (ContactImportStatus $status): ContactImport => ContactImport::factory()->create(['status' => $status]));
    collect([ContactImportStatus::Draft, ContactImportStatus::Completed, ContactImportStatus::Failed, ContactImportStatus::Cancelled])
        ->each(fn (ContactImportStatus $status) => ContactImport::factory()->create(['status' => $status]));
    $this->travelBack();
    ContactImport::factory()->create(['status' => ContactImportStatus::Processing]);

    $this->artisan('contacts:resume-imports')->assertSuccessful();
    $this->artisan('contacts:resume-imports')->assertSuccessful();

    Queue::assertPushed(ProcessContactImport::class, 2);
    expect(Queue::pushed(ProcessContactImport::class)->map(fn (ProcessContactImport $job): int => $job->contactImportId)->sort()->values()->all())
        ->toBe($stalled->pluck('id')->sort()->values()->all());
});

test('the schedule sweeps lost imports every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'contacts:resume-imports'));

    expect($event?->expression)->toBe('*/5 * * * *');
});
