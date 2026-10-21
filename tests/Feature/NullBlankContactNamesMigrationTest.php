<?php

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

test('turns blank contact and subscriber names into null and keeps real names', function () {
    $audience = Audience::factory()->create();
    $blank = Contact::factory()->for($audience->team)->create();
    $named = Contact::factory()->for($audience->team)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $member = Subscriber::factory()->for($audience)->for($blank)->create(['email' => $blank->email]);

    DB::table('contacts')->where('id', $blank->id)->update(['first_name' => '', 'last_name' => '  ']);
    DB::table('subscribers')->where('id', $member->id)->update(['first_name' => '', 'last_name' => '']);

    (require base_path('database/migrations/2026_10_21_054632_null_blank_contact_and_subscriber_names.php'))->up();

    expect($blank->fresh()->only(['first_name', 'last_name']))->toBe(['first_name' => null, 'last_name' => null])
        ->and($member->fresh()->only(['first_name', 'last_name']))->toBe(['first_name' => null, 'last_name' => null])
        ->and($named->fresh()->only(['first_name', 'last_name']))->toBe(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
});
