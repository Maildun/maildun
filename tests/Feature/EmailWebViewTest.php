<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Enums\AutomationAction;
use App\Mail\AutomationEmail;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

function webViewCampaignDelivery(string $html, array $mergeData = []): EmailDelivery
{
    $email = Email::factory()->create(['html' => $html]);

    return EmailDelivery::factory()->for($email)->create(['merge_data' => $mergeData]);
}

test('a campaign body resolves the web view merge tag to a signed link', function () {
    $delivery = webViewCampaignDelivery('<p>Trouble reading? <a href="{{ web_view_url }}">Open in browser</a></p>');

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->not->toContain('{{ web_view_url }}')
        ->and($html)->toContain('href="'.htmlspecialchars(BuildTrackedEmailHtml::webViewUrl($delivery), ENT_QUOTES | ENT_HTML5).'"')
        ->and($html)->toContain('/view/'.$delivery->uuid)
        ->and($html)->toContain('signature=');
});

test('a snapshotted merge field cannot shadow the delivery links', function () {
    $delivery = webViewCampaignDelivery(
        '<a href="{{ web_view_url }}">Browser</a> <a href="{{ unsubscribe_url }}">Bye</a>',
        ['web_view_url' => 'https://evil.example/view', 'unsubscribe_url' => 'https://evil.example/bye'],
    );

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->not->toContain('evil.example')
        ->and($html)->toContain('/view/'.$delivery->uuid)
        ->and($html)->toContain('/unsubscribe/'.$delivery->uuid);
});

test('an unsigned web view link is rejected', function () {
    $campaign = webViewCampaignDelivery('<p>Hello</p>');
    $transactional = TransactionalEmailDelivery::factory()->create([
        'transactional_email_id' => TransactionalEmail::factory()->published()->create()->id,
    ]);
    $run = AutomationRun::factory()->create();
    $automation = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $run->automation->team_id,
    ]);

    $this->get(route('public.web_view.show', ['delivery' => $campaign]))->assertForbidden();
    $this->get(route('public.web_view.transactional.show', ['delivery' => $transactional]))->assertForbidden();
    $this->get(route('public.web_view.automation.show', ['delivery' => $automation]))->assertForbidden();
});

test('a campaign web view serves the tracked body with the attribution notice', function () {
    $delivery = webViewCampaignDelivery(
        '<html><body><p>Hello {{ name }}</p><a href="{{ web_view_url }}">Browser</a></body></html>',
        ['name' => 'Ada'],
    );

    $response = $this->get(BuildTrackedEmailHtml::webViewUrl($delivery))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $html = $response->getContent();

    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($html)->toContain('<p>Hello Ada</p>')
        ->and($html)->not->toContain('{{ web_view_url }}')
        ->and($html)->toContain('/view/'.$delivery->uuid)
        ->and($html)->toContain('/track/emails/'.$delivery->uuid.'/open.gif')
        ->and($html)->toContain('/unsubscribe/'.$delivery->uuid)
        ->and($html)->toContain('Powered by')
        ->and($html)->toContain('href="'.config('attribution.source_url').'"')
        ->and(stripos($html, 'Powered by'))->toBeLessThan(stripos($html, '</body>'));
});

test('a transactional send stores the signed web view link in its body', function () {
    Queue::fake();
    $email = TransactionalEmail::factory()->published()->create([
        'subject' => 'Receipt for {{ first_name }}',
        'html' => '<p>Hi {{ first_name }}</p><a href="{{ web_view_url }}">Open in browser</a>',
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
            // A caller cannot point the link somewhere else.
            'data' => ['first_name' => 'Ada', 'web_view_url' => 'https://evil.example'],
        ])
        ->assertAccepted();

    $delivery = TransactionalEmailDelivery::query()->sole();
    $expectedUrl = URL::signedRoute('public.web_view.transactional.show', ['delivery' => $delivery]);

    expect($delivery->html)->toBe('<p>Hi Ada</p><a href="'.htmlspecialchars($expectedUrl, ENT_QUOTES | ENT_HTML5).'">Open in browser</a>')
        ->and($delivery->html)->not->toContain('evil.example');

    $this->get($expectedUrl)
        ->assertOk()
        ->assertSee('<p>Hi Ada</p>', false)
        ->assertSee('Powered by')
        ->assertSee('href="'.config('attribution.source_url').'"', false);
});

test('the web view tag is never recorded as a caller variable', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = TransactionalEmail::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$team, $email]), [
            'name' => 'Receipt',
            'slug' => 'receipt',
            'subject' => 'Hi {{ first_name }}',
            'html' => '<p>{{ order_number }}</p><a href="{{ web_view_url }}">Browser</a>',
            'variables' => [['key' => 'web_view_url', 'example' => 'https://example.com']],
        ])
        ->assertRedirect();

    expect($email->refresh()->variables)->toBe([
        ['key' => 'first_name', 'example' => ''],
        ['key' => 'order_number', 'example' => ''],
    ]);
});

test('automation mail carries a web view link that re-renders the body', function () {
    Mail::fake();

    $automation = Automation::factory()->active()->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->create([
        'html' => '<p>Welcome {{ first_name }}</p><a href="{{ web_view_url }}">Open in browser</a><a href="{{ unsubscribe_url }}">Bye</a>',
    ]);
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->create(['first_name' => 'Ada']);

    $run = $automation->runs()->create([
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

    app(AdvanceAutomationRun::class)->handle($run);

    $delivery = AutomationEmailDelivery::query()->sole();
    $expectedUrl = URL::signedRoute('public.web_view.automation.show', ['delivery' => $delivery]);

    Mail::assertSent(AutomationEmail::class, function (AutomationEmail $mail) use ($expectedUrl): bool {
        return str_contains($mail->htmlBody, htmlspecialchars($expectedUrl, ENT_QUOTES | ENT_HTML5))
            && ! str_contains($mail->htmlBody, '{{ web_view_url }}');
    });

    $this->get($expectedUrl)
        ->assertOk()
        ->assertSee('<p>Welcome Ada</p>', false)
        ->assertSee('/unsubscribe/s/'.$subscriber->uuid)
        ->assertDontSee('{{ web_view_url }}')
        ->assertSee('Powered by');
});

test('an automation web view is gone once its recipient or template is', function () {
    $run = AutomationRun::factory()->create();
    $email = TransactionalEmail::factory()->for($run->automation->team)->create();
    $orphaned = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $run->automation->team_id,
        'transactional_email_id' => $email->id,
        'subscriber_id' => null,
    ]);
    $templateless = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $run->automation->team_id,
        'transactional_email_id' => null,
        'subscriber_id' => $run->subscriber_id,
    ]);

    $this->get(URL::signedRoute('public.web_view.automation.show', ['delivery' => $orphaned]))->assertNotFound();
    $this->get(URL::signedRoute('public.web_view.automation.show', ['delivery' => $templateless]))->assertNotFound();
});
