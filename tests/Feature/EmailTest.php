<?php

use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Jobs\SendCampaignTestEmail;
use App\Mail\ComposedEmailTest;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailAddressHealth;
use App\Models\EmailTemplate;
use App\Models\Media;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the email list shows the team drafts and the available templates', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Email::factory()->for($team)->create(['name' => 'March newsletter']);
    Email::factory()->for(Team::factory()->create())->create(['name' => 'Other team draft']);
    EmailTemplate::factory()->for($team)->create(['name' => 'House style']);

    $this->actingAs($user)
        ->get(route('emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/index')
            ->where('canManage', true)
            ->where('defaultEditor', EmailEditor::Html->value)
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'March newsletter')
            ->where('emails.data.0.status', 'draft')
            // The HTML starter plus the team's HTML template.
            ->has('templates', 2));
});

test('the campaign list shows sent status and a three person recipient preview', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    $subscribers = Subscriber::factory()->count(63)->for($audience)->create();
    $unsubscribed = Subscriber::factory()->unsubscribed()->for($audience)->create();
    $segment->subscribers()->attach([...$subscribers->modelKeys(), $unsubscribed->id]);

    Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('emails.data.0.status', 'sent')
            ->where('emails.data.0.recipient_count', 63)
            ->has('emails.data.0.recipients', 3)
            ->where('emails.data.0.recipients.0.avatar', $subscribers->first()->avatar));
});

test('composing from a template uses the team editor and copies its body', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    $response = $this->actingAs($user)
        ->post(route('emails.store', $team), [
            'name' => 'Launch announcement',
            'template' => $template->uuid,
        ])
        ->assertRedirect();

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign created.']);

    $email = $team->emails()->firstOrFail();

    expect($email->name)->toBe('Launch announcement')
        ->and($email->subject)->toBe('Launch announcement')
        ->and($email->preheader)->toBeNull()
        ->and($email->editor)->toBe(EmailEditor::Builder)
        ->and($email->email_template_id)->toBe($template->id)
        ->and($email->design)->toBe($template->design);
});

test('composing from a template copies its subject and preheader', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::query()->where('name', 'Newsletter')->whereNull('team_id')->firstOrFail();

    $this->actingAs($user)
        ->post(route('emails.store', $team), [
            'name' => 'April issue',
            'template' => $template->uuid,
        ])
        ->assertRedirect();

    $email = $team->emails()->firstOrFail();

    expect($email->name)->toBe('April issue')
        ->and($email->subject)->toBe('This month at your company')
        ->and($email->preheader)->toBe('Here is what the team has been up to since the last issue.')
        ->and($email->email_template_id)->toBe($template->id);
});

test('composing without a template falls back to the team default editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);

    $this->actingAs($user)
        ->post(route('emails.store', $team), ['name' => 'Empty draft'])
        ->assertRedirect();

    $email = $team->emails()->firstOrFail();

    expect($email->editor)->toBe(EmailEditor::Builder)
        ->and($email->design)->not->toBeNull()
        ->and($email->email_template_id)->toBeNull();
});

test('a template from another team cannot be used as a starting point', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreign = EmailTemplate::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('emails.store', $team), [
            'name' => 'Sneaky draft',
            'template' => $foreign->uuid,
        ])
        ->assertInvalid('template');
});

test('a template using a different editor cannot be used to compose an email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $template = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    $this->actingAs($user)
        ->post(route('emails.store', $team), [
            'name' => 'Launch announcement',
            'template' => $template->uuid,
        ])
        ->assertInvalid('template');
});

test('the compose page exposes the draft, audiences, and sender defaults', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update([
        'email_editor' => EmailEditor::Builder,
        'email_from_name' => 'Maildun HQ',
        'email_from_address' => 'hq@example.com',
    ]);
    $audience = Audience::factory()->for($team)->create([
        'name' => 'Readers',
        'from_name' => 'Readers Desk',
        'from_address' => 'readers@example.com',
        'reply_to' => 'reply@example.com',
    ]);
    TeamSender::factory()->for($team)->create([
        'name' => 'Maildun HQ',
        'email' => 'hq@example.com',
    ]);
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'Campaign Desk',
        'email' => 'campaigns@example.com',
    ]);
    TeamSender::factory()->for($team)->pending()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $segment = Segment::factory()->for($audience)->create(['name' => 'Engaged']);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
    ]);
    Media::factory()->for($team)->create(['name' => 'campaign-hero.png']);
    Media::factory()->for(Team::factory()->create())->create(['name' => 'private.png']);

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/edit')
            ->where('email.uuid', $email->uuid)
            ->where('email.editor', EmailEditor::Builder->value)
            ->where('email.audience', $audience->uuid)
            ->where('email.segment', $segment->uuid)
            ->where('canManage', true)
            ->where('defaults.from_name', 'Maildun HQ')
            ->where('defaults.from_address', 'hq@example.com')
            ->where('selectedSenderUuid', 'audience-default')
            ->where('mediaLibrary.canManage', true)
            ->where('mediaLibrary.canUpload', true)
            ->where('mediaLibrary.atLimit', false)
            ->where('mediaLibrary.items.0.name', 'campaign-hero.png')
            ->has('mediaLibrary.items', 1)
            ->has('audiences', 1)
            ->where('audiences.0.from_name', 'Readers Desk')
            ->where('audiences.0.from_address', 'readers@example.com')
            ->where('audiences.0.reply_to', 'reply@example.com')
            ->has('senders', 2)
            ->where('senders', fn ($senders): bool => collect($senders)->contains('uuid', $sender->uuid))
            ->has('audiences.0.segments', 1));
});

test('a campaign can override its audience sender with a verified workspace sender', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'Audience Desk',
        'from_address' => 'audience@example.com',
    ]);
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'Campaign Desk',
        'email' => 'campaigns@example.com',
        'reply_to' => 'campaign-replies@example.com',
    ]);
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'from_name' => null,
        'from_address' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'audience' => $audience->uuid,
            'sender_uuid' => $sender->uuid,
        ])
        ->assertRedirect();

    expect($email->fresh())
        ->from_name->toBe('Campaign Desk')
        ->from_address->toBe('campaigns@example.com')
        ->reply_to->toBe('campaign-replies@example.com')
        ->resolvedFromAddress()->toBe('campaigns@example.com');
});

test('the campaign sender select preserves an existing custom sender until it is changed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'from_name' => 'Legacy sender',
        'from_address' => 'legacy@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedSenderUuid', 'current-sender'));

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => 'Renamed campaign',
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'sender_uuid' => 'current-sender',
        ])
        ->assertRedirect();

    expect($email->fresh())
        ->name->toBe('Renamed campaign')
        ->from_name->toBe('Legacy sender')
        ->from_address->toBe('legacy@example.com');
});

test('the audience default clears a campaign sender override', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'Audience Desk',
        'from_address' => 'audience@example.com',
        'reply_to' => 'audience-replies@example.com',
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'from_name' => 'Old campaign sender',
        'from_address' => 'old@example.com',
        'reply_to' => 'old-replies@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'audience' => $audience->uuid,
            'sender_uuid' => 'audience-default',
        ])
        ->assertRedirect();

    expect($email->fresh())
        ->from_name->toBeNull()
        ->from_address->toBeNull()
        ->reply_to->toBeNull()
        ->resolvedFromName()->toBe('Audience Desk')
        ->resolvedFromAddress()->toBe('audience@example.com')
        ->resolvedReplyTo()->toBe('audience-replies@example.com');
});

test('campaign sender selection rejects unverified and cross-workspace senders', function (string $senderState) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'from_address' => 'original@example.com',
    ]);
    $sender = $senderState === 'unverified'
        ? TeamSender::factory()->for($team)->pending()->create()
        : TeamSender::factory()->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'sender_uuid' => $sender->uuid,
        ])
        ->assertSessionHasErrors([
            'sender_uuid' => 'Select a verified sender from this workspace.',
        ]);

    expect($email->fresh()->from_address)->toBe('original@example.com');
})->with(['unverified', 'cross workspace']);

test('the compose page counts the subscribed people behind each audience and segment', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();

    $subscribed = Subscriber::factory()->count(3)->for($audience)->create(['status' => SubscriberStatus::Subscribed]);
    Subscriber::factory()->for($audience)->create(['status' => SubscriberStatus::Unsubscribed]);
    $segment->subscribers()->attach($subscribed->take(2));

    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.subscribed_count', 3)
            ->where('audiences.0.segments.0.subscribed_count', 2));
});

test('the compose page leaves suppressed addresses out of recipient counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    $reader = Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    $bounced = Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']);
    $segment->subscribers()->attach([$reader->id, $bounced->id]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.subscribed_count', 1)
            ->where('audiences.0.segments.0.subscribed_count', 1));
});

test('the compose preview renders merge tags with the selected recipient data', function () {
    $this->travelTo('2026-08-29 10:00:00');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'attribute_values' => ['company' => 'Analytical & Engines'],
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello {{ first_name }}',
        'preheader' => '{{ company }} update',
        'html' => '<p>{{ name }} from {{ company }} on {{ day_name }}</p>',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->assertJsonPath('recipient.uuid', $subscriber->uuid)
        ->assertJsonPath('recipient.email', 'ada@example.com')
        ->assertJsonPath('subject', 'Hello Ada')
        ->assertJsonPath('preheader', 'Analytical & Engines update');

    expect(array_is_list($response->json('recipients')))->toBeTrue();

    expect($response->json('html'))
        ->toContain('Ada Lovelace')
        ->toContain('Analytical &amp; Engines')
        ->toContain('Saturday')
        ->not->toContain('{{');
});

test('the compose preview fills personalization and delivery link tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hi {{ first_name }}</p><a href="{{ unsubscribe_url }}">Unsubscribe</a><a href="{{ web_view_url }}">View</a>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('Hi Ada')
        ->toContain('href="#unsubscribe"')
        ->toContain('href="#web-view"')
        ->not->toContain('{{');

    expect(substr_count((string) $html, 'href="#unsubscribe"'))->toBe(1);
});

test('the compose preview keeps an html unsubscribe anchor clickable', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="{{ unsubscribe_url }}">Unsubscribe here</a></p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#unsubscribe"')
        ->toContain('Unsubscribe here')
        ->not->toContain('{{');

    expect(substr_count((string) $html, 'href="#unsubscribe"'))->toBe(1);
});

test('the compose preview hydrates leftover markdown unsubscribe tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>[An Internal Link]({{ unsubscribe_url }})</p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#unsubscribe"')
        ->toContain('An Internal Link')
        ->not->toContain('[An Internal Link]');
});

test('the compose preview keeps a markdown-rendered unsubscribe link clickable', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="{{ unsubscribe_url }}" target="_blank">Unsubscribe</a></p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#unsubscribe"')
        ->toContain('Unsubscribe')
        ->not->toContain('{{');
});

test('the compose preview shows a subscribe link even without a published form', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="{{ subscribe_url }}">Subscribe here</a></p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#subscribe"')
        ->toContain('Subscribe here')
        ->not->toContain('{{');
});

test('the compose preview opens the published subscribe form from the tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $form = SubscribeForm::factory()->for($audience)->published()->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="{{ subscribe_url }}">Subscribe here</a></p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="'.route('public.subscribe_forms.show', $form).'"')
        ->toContain('Subscribe here')
        ->not->toContain('{{');
});

test('the compose preview appends an unsubscribe footer when the author did not place one', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello {{ first_name }}</p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#unsubscribe"')
        ->toContain('Unsubscribe')
        ->not->toContain('{{');
});

test('the compose preview restores markdown-encoded merge tags in links', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'first_name' => 'Ada',
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="%7B%7B%20unsubscribe_url%20%7D%7D">Unsubscribe</a> {{ first_name }}</p>',
    ]);

    $html = $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $subscriber->uuid,
        ]))
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('href="#unsubscribe"')
        ->toContain('Ada')
        ->not->toContain('%7B%7B')
        ->not->toContain('{{');
});

test('the preview and send page opens with the first personalized recipient', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $first = Subscriber::factory()->for($audience)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $second = Subscriber::factory()->for($audience)->create([
        'email' => 'grace@example.com',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);
    $email = Email::factory()->for($team)->create([
        'name' => 'Personalized launch',
        'audience_id' => $audience->id,
        'subject' => 'Hello {{ first_name }}',
        'html' => '<p>Welcome {{ name }}</p>',
    ]);

    $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/preview-and-send')
            ->where('campaign.uuid', $email->uuid)
            ->where('campaign.name', 'Personalized launch')
            ->where('recipientCount', 2)
            ->where('preview.recipient.uuid', $first->uuid)
            ->where('preview.subject', 'Hello Ada')
            ->where('preview.navigation.previous', null)
            ->where('preview.navigation.next', $second->uuid)
            ->where('preview.navigation.position', 1)
            ->where('missingUnsubscribe', true));
});

test('the preview and send page skips suppressed recipients and says why', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $reader = Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'gone@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'complained@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create([
        'email' => 'bounced@example.com',
        'reason' => EmailAddressHealthReason::PermanentBounce,
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create([
        'email' => 'gone@example.com',
        'reason' => EmailAddressHealthReason::PermanentBounce,
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create([
        'email' => 'complained@example.com',
        'reason' => EmailAddressHealthReason::Complaint,
    ]);
    $email = Email::factory()->for($team)->create(['audience_id' => $audience->id]);

    $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipientCount', 1)
            ->where('preview.recipient.uuid', $reader->uuid)
            ->where('preview.navigation.next', null)
            ->where('suppressedRecipients', [
                'count' => 3,
                'reasons' => [
                    ['label' => 'Permanent bounce', 'count' => 2],
                    ['label' => 'Spam complaint', 'count' => 1],
                ],
            ]));
});

test('the preview and send page counts unconfirmed double opt-in signups separately', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => true]);
    Subscriber::factory()->for($audience)->create();
    Subscriber::factory()->for($audience)->pendingConfirmation()->count(2)->create();
    Subscriber::factory()->for($audience)->pendingConfirmation()->create(['email' => 'pending-bounced@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'pending-bounced@example.com']);
    $email = Email::factory()->for($team)->create(['audience_id' => $audience->id]);

    $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipientCount', 1)
            ->where('unconfirmedRecipients', 3)
            ->where('suppressedRecipients.count', 0));
});

test('the preview and send page flags a missing unsubscribe tag', function (string $html, bool $missing) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => $html,
    ]);

    $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/preview-and-send')
            ->where('missingUnsubscribe', $missing));
})->with([
    'plain body' => ['<p>Hello</p>', true],
    'author placed tag' => ['<p><a href="{{ unsubscribe_url }}">Unsubscribe</a></p>', false],
    'percent-encoded tag' => ['<p><a href="%7B%7B%20unsubscribe_url%20%7D%7D">Unsubscribe</a></p>', false],
]);

test('the preview and send page returns to setup when no subscribed recipients exist', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->unsubscribed()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertRedirect(route('emails.edit', [$team, $email]));

    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'This campaign has no subscribed recipients.',
    ]);
});

test('the compose preview rejects a recipient outside the selected segment', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    $included = Subscriber::factory()->for($audience)->create();
    $excluded = Subscriber::factory()->for($audience)->create();
    $segment->subscribers()->attach($included);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.compose-preview', [
            $team,
            $email,
            'recipient' => $excluded->uuid,
        ]))
        ->assertNotFound();
});

test('an email can be updated with recipients and html content', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create();

    $response = $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => 'Renamed draft',
            'subject' => 'Hello there',
            'preheader' => 'A short preview',
            'html' => '<p>Body copy</p>',
            'audience' => $audience->uuid,
            'segment' => $segment->uuid,
        ])
        ->assertRedirect();

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign saved.']);

    $email->refresh();

    expect($email->name)->toBe('Renamed draft')
        ->and($email->subject)->toBe('Hello there')
        ->and($email->html)->toBe('<p>Body copy</p>')
        ->and($email->audience_id)->toBe($audience->id)
        ->and($email->segment_id)->toBe($segment->id);
});

test('saving an email uses the team editor and drops an incompatible block document', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->builder()->create();

    expect($email->design)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Hand written</p>',
        ])
        ->assertRedirect();

    expect($email->fresh()->editor)->toBe(EmailEditor::Html)
        ->and($email->fresh()->design)->toBeNull();
});

test('a markdown campaign keeps its source and rendered html', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Markdown]);
    $email = Email::factory()->for($team)->builder()->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'source' => '# Hello **there**',
            'html' => '<h1>Hello <strong>there</strong></h1>',
        ])
        ->assertRedirect();

    $email->refresh();

    expect($email->editor)->toBe(EmailEditor::Markdown)
        ->and($email->source)->toBe('# Hello **there**')
        ->and($email->html)->toBe('<h1>Hello <strong>there</strong></h1>')
        ->and($email->design)->toBeNull();

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.editor', EmailEditor::Markdown->value)
            ->where('email.source', '# Hello **there**'));
});

test('a plain text campaign uses its source as the text alternative', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::PlainText]);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'source' => "Hello there\nSecond line",
            'html' => '<div>Hello there<br>Second line</div>',
        ])
        ->assertRedirect();

    $email->refresh();

    expect($email->editor)->toBe(EmailEditor::PlainText)
        ->and($email->source)->toBe("Hello there\nSecond line")
        ->and($email->plain_text)->toBe("Hello there\nSecond line");
});

test('a source-based draft saves before its body is written', function (EmailEditor $editor) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => $editor]);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => 'Renamed draft',
            'subject' => $email->subject,
            'source' => '',
            'html' => '<p>Rendered</p>',
        ])
        ->assertValid('source')
        ->assertRedirect();

    $email->refresh();

    expect($email->name)->toBe('Renamed draft')
        ->and($email->editor)->toBe($editor)
        ->and($email->source)->toBeNull()
        ->and($email->plain_text)->toBeNull();
})->with([
    'plain text' => EmailEditor::PlainText,
    'markdown' => EmailEditor::Markdown,
]);

test('the block editor requires a document', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>rendered</p>',
        ])
        ->assertInvalid('design');
});

test('a segment must belong to the chosen audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($otherAudience)->create();
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'audience' => $audience->uuid,
            'segment' => $segment->uuid,
        ])
        ->assertInvalid('segment');
});

test('a segment without an audience is rejected', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'segment' => $segment->uuid,
        ])
        ->assertInvalid('segment');
});

test('an audience from another team cannot be attached to an email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreignAudience = Audience::factory()->for(Team::factory()->create())->create();
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => $email->subject,
            'html' => '<p>Body</p>',
            'audience' => $foreignAudience->uuid,
        ])
        ->assertInvalid('audience');
});

test('a test send uses the audience sender when the draft and team leave it blank', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_from_name' => 'Maildun HQ', 'email_from_address' => 'hq@example.com']);
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'Readers Desk',
        'from_address' => 'readers@example.com',
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'from_name' => null,
        'from_address' => null,
    ]);

    $this->actingAs($user)
        ->post(route('emails.test', [$team, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    Queue::assertPushedOn('transactional', SendCampaignTestEmail::class, fn (SendCampaignTestEmail $job): bool => $job->recipient === 'reviewer@example.com');
});

test('a test send turns leftover markdown unsubscribe tags into https links', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'html' => '<p>You get this email because you subscribe to dibma updates. [An Internal Link]({{ unsubscribe_url }})</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.test', [$team, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    Queue::assertPushedOn('transactional', SendCampaignTestEmail::class, function (SendCampaignTestEmail $job): bool {
        $unsubscribe = route('public.unsubscribe.test');

        return str_contains($job->html, 'href="'.$unsubscribe.'"')
            && str_contains($job->html, 'An Internal Link')
            && ! str_contains($job->html, '#unsubscribe')
            && ! str_contains($job->html, '[An Internal Link]');
    });
});

test('a test send queues the resolved sender identity', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_from_name' => 'Maildun HQ', 'email_from_address' => 'hq@example.com']);
    $email = Email::factory()->for($team)->create(['from_name' => null, 'from_address' => null]);

    $this->actingAs($user)
        ->post(route('emails.test', [$team, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    Queue::assertPushedOn('transactional', SendCampaignTestEmail::class, fn (SendCampaignTestEmail $job): bool => $job->emailId === $email->id
        && $job->recipient === 'reviewer@example.com'
        && $job->subject === $email->subject);

    expect($email->fresh()->last_tested_at)->toBeNull();
});

test('a test send requires a valid address', function () {
    Mail::fake();

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), ['to' => 'not-an-address'])
        ->assertInvalid('to');

    Mail::assertNothingSent();
});

test('a campaign test send queues before the delivery worker resolves its provider', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('emails.test', [$team, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    Queue::assertPushedOn('transactional', SendCampaignTestEmail::class);
    expect($email->fresh()->last_tested_at)->toBeNull();
});

test('a queued campaign test send delivers one copy through the workspace mailer', function () {
    Mail::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->ses()->create();
    $email = Email::factory()->for($team)->create([
        'subject' => 'Hello Ada',
        'html' => '<p>Welcome</p>',
    ]);

    app()->call([new SendCampaignTestEmail(
        $email->id,
        'reviewer@example.com',
        'Hello Ada',
        '<p>Welcome</p>',
        'Welcome',
    ), 'handle']);

    Mail::assertSent(ComposedEmailTest::class, fn (ComposedEmailTest $mail): bool => $mail->hasTo('reviewer@example.com')
        && $mail->envelope()->subject === '[Test] Hello Ada');
    expect($email->fresh()->last_tested_at)->not->toBeNull();
});

test('an email can be deleted', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $response = $this->actingAs($user)
        ->delete(route('emails.destroy', [$user->currentTeam, $email]))
        ->assertRedirect(route('emails.index', $user->currentTeam));

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Campaign deleted.']);

    $this->assertSoftDeleted($email);
});

test('a campaign cannot be deleted while it is queued or sending', function (EmailStatus $status) {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => $status]);

    $this->actingAs($user)
        ->delete(route('emails.destroy', [$user->currentTeam, $email]))
        ->assertForbidden();

    $this->assertNotSoftDeleted($email);
})->with([
    'queued' => [EmailStatus::Queued],
    'sending' => [EmailStatus::Sending],
]);

test('a queued campaign opens its report and cannot be edited', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => 'queued',
        'recipient_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('emails.edit', [$user->currentTeam, $email]))
        ->assertRedirect(route('emails.show', [$user->currentTeam, $email]));

    $this->actingAs($user)
        ->patch(route('emails.update', [$user->currentTeam, $email]), [
            'name' => 'Changed too late',
            'subject' => 'Changed too late',
            'html' => '<p>Changed</p>',
        ])
        ->assertConflict();
});

test('members can read emails but cannot change or send them', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('emails.store', $team), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => 'Nope',
            'subject' => 'Nope',
            'html' => '<p>Nope</p>',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('emails.test', [$team, $email]), ['to' => 'member@example.com'])
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('emails.compose-preview', [$team, $email]))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('emails.destroy', [$team, $email]))
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('emails cannot be reached through another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('emails.edit', [$otherTeam, $email]))
        ->assertNotFound();
});

test('non members cannot open the email list', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);

    $this->actingAs(User::factory()->create())
        ->get(route('emails.index', $team))
        ->assertForbidden();
});

test('guests are redirected from the email list', function () {
    $team = Team::factory()->create();

    $this->get(route('emails.index', $team))->assertRedirect(route('login'));
});

test('the campaign list can be searched and filtered by status audience and editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'Product updates']);
    $otherAudience = Audience::factory()->for($team)->create(['name' => 'Investors']);

    Email::factory()->for($team)->create(['name' => 'March newsletter', 'audience_id' => $audience->id]);
    Email::factory()->for($team)->create([
        'name' => 'February recap',
        'audience_id' => $otherAudience->id,
        'sent_at' => now(),
    ]);
    Email::factory()->builder()->for($team)->create(['name' => 'Block draft']);

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'q' => 'march']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'March newsletter')
            ->where('filters.q', 'march')
            ->has('audiences', 2)
            ->where('audiences.0.name', 'Investors'));

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'status' => 'sent']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'February recap')
            ->where('emails.data.0.status', 'sent')
            ->where('filters.status', 'sent'));

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 2));

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'audience' => $audience->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'March newsletter')
            ->where('filters.audience', $audience->uuid));

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'editor' => EmailEditor::Builder->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'Block draft')
            ->where('filters.editor', EmailEditor::Builder->value));
});

test('an audience filter from another team matches no campaign', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Email::factory()->for($team)->create(['name' => 'March newsletter']);
    $foreign = Audience::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'audience' => $foreign->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('emails.data', 0));
});
