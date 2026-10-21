<?php

use App\Actions\Automations\BuildAutomationMergeData;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use App\Models\Team;

function automationMergeDelivery(Subscriber $subscriber): AutomationEmailDelivery
{
    $automation = Automation::factory()->for($subscriber->audience->team)->create();
    $run = AutomationRun::factory()->for($automation)->for($subscriber)->create();

    return AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $subscriber->audience->team_id,
        'subscriber_id' => $subscriber->id,
        'to_address' => $subscriber->email,
    ]);
}

test('stored attributes cannot replace the built-in subscriber fields', function () {
    $audience = Audience::factory()->for(Team::factory())->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'victim@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'attribute_values' => [
            'email' => 'spoof@example.com',
            'first_name' => 'Mallory',
            'last_name' => 'Spoof',
            'company' => 'Acme',
        ],
    ]);

    $data = app(BuildAutomationMergeData::class)->handle($subscriber, [], automationMergeDelivery($subscriber));

    expect($data)
        ->email->toBe('victim@example.com')
        ->first_name->toBe('Ada')
        ->last_name->toBe('Lovelace')
        ->company->toBe('Acme');
});
