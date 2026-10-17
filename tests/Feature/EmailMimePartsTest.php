<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Enums\AutomationAction;
use App\Enums\EmailStatus;
use App\Jobs\SendEmailDelivery;
use App\Jobs\SendTransactionalEmailTest;
use App\Mail\AutomationEmail;
use App\Mail\CampaignEmail;
use App\Mail\ComposedEmailTest;
use App\Mail\TransactionalEmailMessage;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * Render a captured mailable into the MIME message a transport would carry.
 */
function sentMimeMessage(Mailable $mailable): MimeEmail
{
    $transport = new ArrayTransport;

    $mailable->send(new Mailer('mime-parts', app('view'), $transport));

    return $transport->messages()->sole()->getOriginalMessage();
}

/**
 * A document shaped like @maildun/email-builder output: head styles, MSO
 * comments, and the editor's own hidden preheader leading the body.
 */
function builderEmailHtml(string $content): string
{
    return implode("\n", [
        '<!DOCTYPE html>',
        '<html lang="en">',
        '<head>',
        '<meta charset="utf-8">',
        '<title>Newsletter</title>',
        '<style>.meb-canvas{color:#262626}</style>',
        '</head>',
        '<body style="margin:0;padding:0;background-color:#f5f5f5">',
        '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">From the editor&#847;&zwnj;&nbsp;</div>',
        '<!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->',
        '<table role="presentation" class="meb-canvas" width="100%"><tr><td>'.$content.'</td></tr></table>',
        '<!--[if mso]></td></tr></table><![endif]-->',
        '</body>',
        '</html>',
    ]);
}

/**
 * @param  array<string, mixed>  $emailAttributes
 * @param  array<string, mixed>  $mergeData
 */
function queuedCampaignDelivery(array $emailAttributes, array $mergeData = []): EmailDelivery
{
    $audience = Audience::factory()->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($audience->team)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::Sending,
        ...$emailAttributes,
    ]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();

    return EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $subscriber->id,
        'email_address' => $subscriber->email,
        'merge_data' => $mergeData,
    ]);
}

/**
 * @param  array<string, mixed>  $emailAttributes
 */
function automationSendRun(array $emailAttributes, Subscriber $subscriber): AutomationRun
{
    $automation = Automation::factory()->active()->for($subscriber->audience->team)->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->published()->create($emailAttributes);

    return $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
                ['id' => 'send', 'type' => 'action', 'data' => [
                    'kind' => AutomationAction::SendEmail->value,
                    'transactional_email_uuid' => $email->uuid,
                ]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'send']],
        ],
        'current_node_id' => 'send',
    ]);
}

test('a campaign shows its preheader as hidden preview text where the body opens', function () {
    Mail::fake();
    $delivery = queuedCampaignDelivery([
        'preheader' => 'This week, {{ first_name }}',
        'html' => '<html><body><p>Body copy</p></body></html>',
    ], ['first_name' => 'Ada']);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    $html = sentMimeMessage(Mail::sent(CampaignEmail::class)->sole())->getHtmlBody();

    expect($html)->toContain('<body><div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">This week, Ada&#847;&zwnj;&nbsp;')
        ->and(substr_count($html, 'display:none'))->toBe(1);
});

test('a campaign sent from the block editor carries a single preheader', function (?string $preheader, string $shown, string $dropped) {
    Mail::fake();
    $delivery = queuedCampaignDelivery([
        'preheader' => $preheader,
        'html' => builderEmailHtml('<p>Body copy</p>'),
    ]);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    $html = sentMimeMessage(Mail::sent(CampaignEmail::class)->sole())->getHtmlBody();

    expect($html)->toContain('mso-hide:all">'.$shown)
        ->not->toContain($dropped)
        ->and(substr_count($html, 'mso-hide:all'))->toBe(1);
})->with([
    'the campaign preheader replaces the editor one' => ['From the campaign', 'From the campaign', 'From the editor'],
    'the editor preheader stays when the campaign has none' => [null, 'From the editor', 'From the campaign'],
]);

test('a campaign text part follows the html links with real signed urls and no markup', function () {
    Mail::fake();
    $delivery = queuedCampaignDelivery([
        'plain_text' => null,
        'html' => builderEmailHtml('<p>Hi {{ first_name }}</p><p><a href="{{ web_view_url }}">View online</a> · <a href="{{ unsubscribe_url }}">Leave the list</a></p>'),
    ], ['first_name' => 'Ada']);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    $text = sentMimeMessage(Mail::sent(CampaignEmail::class)->sole())->getTextBody();

    expect($text)->toStartWith('Hi Ada')
        ->not->toContain('{{')
        ->not->toContain('From the editor')
        ->not->toContain('#262626')
        ->not->toContain('Unsubscribe:')
        ->not->toMatch('/<\/?[a-z!][^>]*>/i')
        ->toMatch('/View online \(\S+\)\s+· Leave the list \(\S+\)/');

    preg_match('/View online \((\S+)\)\s+· Leave the list \((\S+)\)/', $text, $links);

    $this->get($links[1])->assertOk();
    $this->get($links[2])->assertOk();
});

test('a campaign text part written by the author gets a signed opt-out appended', function () {
    Mail::fake();
    $delivery = queuedCampaignDelivery([
        'plain_text' => "Hi {{ first_name }},\n\nRead it [in your browser]({{ web_view_url }}).",
    ], ['first_name' => 'Ada']);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    $text = sentMimeMessage(Mail::sent(CampaignEmail::class)->sole())->getTextBody();

    expect($text)->toStartWith("Hi Ada,\n\nRead it [in your browser](http")
        ->not->toContain('{{')
        ->not->toContain('<a')
        ->toMatch('/\n\nUnsubscribe: \S+$/');

    preg_match('/Unsubscribe: (\S+)$/', $text, $optOut);

    $this->get($optOut[1])->assertOk();
});

test('a campaign test copy shows the preheader and links its text part to pages that opt nobody out', function () {
    Mail::fake();
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->ses()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'preheader' => 'Preview for {{ first_name }}',
        'plain_text' => null,
        'html' => '<html><body><p>Hi</p><a href="{{ unsubscribe_url }}">Leave</a></body></html>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    $message = sentMimeMessage(Mail::sent(ComposedEmailTest::class)->sole());

    expect($message->getHtmlBody())->toContain('mso-hide:all">Preview for Sample&#847;');
    expect($message->getTextBody())->toBe("Hi\nLeave (".route('public.unsubscribe.test').")\n");
});

test('transactional mail shows its preheader as hidden preview text with caller data escaped', function () {
    Mail::fake();
    $email = TransactionalEmail::factory()->published()->create([
        'preheader' => 'Receipt for {{ first_name }}',
        'html' => '<html><body><p>Paid</p></body></html>',
    ]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $sender = TeamSender::factory()->for($email->team)->create();
    $email->team->forceFill([
        'active_sender_id' => $sender->id,
        'email_from_address' => $sender->email,
    ])->save();
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'ada@example.com',
            'data' => ['first_name' => '<Ada>'],
        ])
        ->assertAccepted();

    $html = sentMimeMessage(Mail::sent(TransactionalEmailMessage::class)->sole())->getHtmlBody();

    expect($html)->toContain('<body><div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">Receipt for &lt;Ada&gt;&#847;')
        ->not->toContain('<Ada>');
});

test('a transactional test copy shows the preheader', function () {
    Queue::fake([SendTransactionalEmailTest::class]);
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create([
        'preheader' => 'Preview for {{ first_name }}',
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('transactional_emails.test', [$user->currentTeam, $email]), [
            'to' => 'reviewer@example.com',
            'data' => ['first_name' => 'Ada'],
        ])
        ->assertRedirect();

    Queue::assertPushed(SendTransactionalEmailTest::class, fn (SendTransactionalEmailTest $job): bool => str_starts_with(
        $job->html,
        '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">Preview for Ada&#847;',
    ) && str_ends_with($job->html, '<p>Hello</p>'));
});

test('automation mail without an unsubscribe tag gets the campaign footer, its preheader and a text part', function () {
    Mail::fake();
    $subscriber = Subscriber::factory()->for(Audience::factory())->create(['first_name' => 'Ada']);
    $run = automationSendRun([
        'preheader' => 'Welcome aboard, {{ first_name }}',
        'html' => builderEmailHtml('<p>Welcome {{ first_name }}</p>'),
    ], $subscriber);

    app(AdvanceAutomationRun::class)->handle($run);

    $message = sentMimeMessage(Mail::sent(AutomationEmail::class)->sole());
    $html = $message->getHtmlBody();
    $text = $message->getTextBody();

    expect($html)->toContain('mso-hide:all">Welcome aboard, Ada&#847;')
        ->not->toContain('From the editor')
        ->toMatch('/<a href="[^"]*\/unsubscribe\/s\/'.$subscriber->uuid.'\?signature=[^"]+"[^>]*>Unsubscribe<\/a><\/div><\/body>/');
    expect($text)->toStartWith('Welcome Ada')
        ->not->toContain('{{')
        ->not->toContain('Welcome aboard')
        ->not->toMatch('/<\/?[a-z!][^>]*>/i')
        ->toMatch('/Unsubscribe \(\S+\/unsubscribe\/s\/'.$subscriber->uuid.'\?signature=\S+\)$/');

    preg_match('/Unsubscribe \((\S+)\)$/', $text, $optOut);

    $this->get($optOut[1])->assertOk();
});

test('automation mail with an author placed opt-out gets no second footer', function () {
    Mail::fake();
    $subscriber = Subscriber::factory()->for(Audience::factory())->create();
    $run = automationSendRun([
        'html' => '<html><body><p>Hello</p><a href="{{ unsubscribe_url }}">Leave</a></body></html>',
    ], $subscriber);

    app(AdvanceAutomationRun::class)->handle($run);

    $message = sentMimeMessage(Mail::sent(AutomationEmail::class)->sole());

    expect(substr_count($message->getHtmlBody(), '/unsubscribe/s/'.$subscriber->uuid))->toBe(1);
    expect($message->getTextBody())->toMatch('/^Hello\nLeave \(\S+\/unsubscribe\/s\/'.$subscriber->uuid.'\?signature=\S+\)$/');
});
