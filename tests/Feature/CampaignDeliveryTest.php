<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\FinalizeEmailSend;
use App\Actions\Emails\RebuildEmailTrackingAggregates;
use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Emails\RetryEmailDeliveries;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailEditor;
use App\Enums\EmailFailureCode;
use App\Enums\EmailProvider;
use App\Enums\EmailSendRunKind;
use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Exceptions\EmailTransportException;
use App\Jobs\PrepareEmailSendChunk;
use App\Jobs\SendEmailDelivery;
use App\Mail\CampaignEmail;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailAddressHealth;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailSendRun;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\ResolvedEmailTransport;
use App\Services\TeamMailer;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Header\MetadataHeader;

test('a campaign queues bounded background preparation before snapshotting recipients', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->count(2)->for($audience)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Read <a href="https://example.com/news">the news</a>.</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertRedirect(route('emails.show', [$team, $email]));

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof PrepareEmailSendChunk
        && $batch->queue() === 'campaigns');

    expect($email->fresh()->status)->toBe(EmailStatus::Queued)
        ->and($email->fresh()->recipient_count)->toBe(2)
        ->and($email->deliveries()->count())->toBe(0);

    [$loader, $batch] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($batch->added)->toHaveCount(2);
    expect($batch->added)->each->toBeInstanceOf(SendEmailDelivery::class);
    expect($email->deliveries()->count())->toBe(2)
        ->and($email->deliveries()->where('provider', 'smtp')->count())->toBe(2)
        ->and($email->links()->value('url'))->toBe('https://example.com/news');
});

test('a campaign skips addresses its workspace has suppressed', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'other-workspace@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);
    EmailAddressHealth::factory()->suppressed()->create(['email' => 'other-workspace@example.com']);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertRedirect(route('emails.show', [$team, $email]));

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($email->fresh()->recipient_count)->toBe(2);
    expect($email->deliveries()->orderBy('email_address')->pluck('email_address')->all())
        ->toBe(['other-workspace@example.com', 'reader@example.com']);
});

test('a campaign skips double opt-in signups who never confirmed', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => true]);
    Subscriber::factory()->for($audience)->create(['email' => 'confirmed@example.com']);
    Subscriber::factory()->for($audience)->pendingConfirmation()->create(['email' => 'pending@example.com']);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertRedirect(route('emails.show', [$team, $email]));

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($email->fresh()->recipient_count)->toBe(1);
    expect($email->deliveries()->pluck('email_address')->all())->toBe(['confirmed@example.com']);
});

test('a campaign whose only subscribers are suppressed cannot be queued', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors(['email' => 'This campaign has no subscribed recipients.']);

    Bus::assertNothingBatched();

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
});

test('a campaign can be queued with a supported workspace email provider', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'sespasswordXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'configuration_set' => 'maildun-production',
            'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-events',
        ],
    ]);
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello from the workspace provider.</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertRedirect(route('emails.show', [$team, $email]));

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($email->deliveries()->sole()->provider)->toBe(EmailProvider::AmazonSes->value);
    expect($email->deliveries()->sole()->uses_team_email_integration)->toBeTrue();
});

test('campaign preparation hydrates large batches one bounded recipient page at a time', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    Subscriber::factory()->count(201)->for($audience)->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::Queued,
        'recipient_count' => 201,
        'send_started_at' => now(),
    ]);

    [$loader, $batch] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    $nextLoader = collect($batch->added)->first(fn (object $job): bool => $job instanceof PrepareEmailSendChunk);

    expect($email->deliveries()->count())->toBe(200)
        ->and($batch->added)->toHaveCount(201)
        ->and($nextLoader)->toBeInstanceOf(PrepareEmailSendChunk::class)
        ->and($nextLoader->afterSubscriberId)->toBeGreaterThan(0);
});

test('a campaign cannot be queued twice', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::Queued]);

    $this->actingAs($user)
        ->post(route('emails.send', [$user->currentTeam, $email]))
        ->assertSessionHasErrors('email');
});

test('a source-based campaign cannot be queued before its body is written', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::PlainText]);
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'editor' => EmailEditor::PlainText,
        'html' => '<div></div>',
        'source' => null,
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertSessionHasErrors('email');

    Bus::assertNothingBatched();

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
});

test('members cannot queue a campaign', function () {
    Bus::fake();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('emails.send', [$team, $email]))
        ->assertForbidden();

    Bus::assertNothingBatched();
});

test('the campaign report exposes dedicated overview, recipient, link, and preview pages', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'first_name' => 'Reader',
        'last_name' => 'Example',
    ]);
    $email = Email::factory()->for($user->currentTeam)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $delivery = $email->deliveries()->create([
        'subscriber_id' => $subscriber->id,
        'email_address' => 'reader@example.com',
        'first_name' => 'Reader',
        'last_name' => 'Example',
        'status' => 'delivered',
        'provider' => 'ses',
        'sent_at' => now(),
        'delivered_at' => now(),
        'first_opened_at' => now(),
        'first_clicked_at' => now(),
        'opens_count' => 2,
        'clicks_count' => 1,
    ]);
    $link = $email->links()->create([
        'url' => 'https://example.com',
        'url_hash' => hash('sha256', 'https://example.com'),
        'position' => 0,
    ]);
    $link->clicks()->create([
        'email_delivery_id' => $delivery->id,
        'clicks_count' => 1,
        'first_clicked_at' => now(),
        'last_clicked_at' => now(),
    ]);
    app(RebuildEmailTrackingAggregates::class)->handle($email);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/show')
            ->where('campaign.status', 'sent')
            ->where('campaign.provider', 'ses')
            ->where('campaign.uses_team_email_integration', false)
            ->where('metrics.opened', 1)
            ->where('metrics.clicked', 1)
            ->where('metrics.delivery_feedback', 'available')
            ->where('metrics.feedback_recipient_count', 1)
            ->where('metrics.delivery_rate', 100)
            ->where('metrics.failed', 0)
            ->where('metrics.retryable', 0)
            ->missing('overview'));

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/recipients')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.avatar', $subscriber->avatar)
            ->where('recipients.data.0.can_retry', false));

    $this->actingAs($user)
        ->get(route('emails.links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/links')
            ->where('links.0.clicks', 1)
            ->where('links.0.unique_clicks', 1));

    $this->actingAs($user)
        ->get(route('emails.preview', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/preview')
            ->where('campaign.html', $email->html));
});

test('a campaign report identifies deliveries sent through mixed providers', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
    ]);

    EmailDelivery::factory()->for($email)->create([
        'provider' => EmailProvider::AmazonSes->value,
        'status' => EmailDeliveryStatus::Delivered,
        'delivered_at' => now(),
        'uses_team_email_integration' => false,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'provider' => EmailProvider::Smtp->value,
        'status' => EmailDeliveryStatus::Sent,
        'sent_at' => now(),
        'uses_team_email_integration' => true,
    ]);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaign.provider', 'mixed')
            ->where('campaign.uses_team_email_integration', true)
            ->where('metrics.delivery_feedback', 'partial')
            ->where('metrics.feedback_recipient_count', 1)
            ->where('metrics.delivered', 1)
            ->where('metrics.delivery_rate', 100));
});

test('an SMTP campaign report does not claim delivery feedback', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
    ]);

    EmailDelivery::factory()->for($email)->create([
        'provider' => EmailProvider::Smtp->value,
        'status' => EmailDeliveryStatus::Sent,
        'sent_at' => now(),
        'delivered_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaign.provider', 'smtp')
            ->where('metrics.delivery_feedback', 'unavailable')
            ->where('metrics.feedback_recipient_count', 0)
            ->where('metrics.delivered', 0)
            ->where('metrics.delivery_rate', null));
});

test('a delivery job sends one tracked message through the configured mailer', function () {
    Mail::fake();
    $email = Email::factory()->create(['html' => '<a href="https://example.com">Visit</a>']);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $delivery = $email->deliveries()->create([
        'email_address' => 'reader@example.com',
        'status' => 'queued',
        'provider' => 'smtp',
    ]);
    $email->links()->create([
        'url' => 'https://example.com',
        'url_hash' => hash('sha256', 'https://example.com'),
        'position' => 0,
    ]);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail): bool => $mail->hasTo('reader@example.com')
        && str_contains($mail->trackedHtml, '/track/emails/'));
    expect($delivery->fresh()->status->value)->toBe('sent');
});

test('ses messages carry the configuration set and delivery correlation tags', function () {
    $email = Email::factory()->create();
    $delivery = $email->deliveries()->create([
        'email_address' => 'reader@example.com',
        'status' => 'queued',
        'provider' => 'ses',
    ]);
    $attempt = EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->ses()->create([
        'ses_configuration_set' => 'maildun-production',
    ]);

    $mailable = new CampaignEmail($delivery, '<p>Tracked</p>', $attempt);

    expect($mailable->envelope()->metadata)->toBe([
        'campaign_uuid' => $email->uuid,
        'delivery_uuid' => $delivery->uuid,
        'attempt_uuid' => $attempt->uuid,
    ])->and($mailable->headers()->text)->not->toHaveKeys([
        'X-SES-CONFIGURATION-SET',
        'X-SES-MESSAGE-TAGS',
    ]);
});

test('ses correlation tags reach the message as the metadata headers the api transport reads', function () {
    $email = Email::factory()->create();
    $delivery = $email->deliveries()->create([
        'email_address' => 'reader@example.com',
        'status' => 'queued',
        'provider' => 'ses',
    ]);
    $attempt = EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->ses()->create([
        'ses_configuration_set' => 'maildun-production',
    ]);

    Mail::mailer('array')
        ->to('reader@example.com')
        ->send(new CampaignEmail($delivery, '<p>Tracked</p>', $attempt));

    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
    $headers = collect($sent)->sole()->getOriginalMessage()->getHeaders();

    // SesTransport and SesV2Transport build their message tags from
    // MetadataHeader instances alone, so this is the only form that survives
    // the API call and comes back on the SNS notification as mail.tags.
    expect(collect($headers->all())->filter(fn ($header): bool => $header instanceof MetadataHeader)
        ->mapWithKeys(fn (MetadataHeader $header): array => [$header->getKey() => $header->getBodyAsString()])
        ->all())
        ->toBe([
            'campaign_uuid' => $email->uuid,
            'delivery_uuid' => $delivery->uuid,
            'attempt_uuid' => $attempt->uuid,
        ]);
});

test('the resolved ses transport carries the workspace configuration set, not the platform one', function () {
    config()->set('services.ses.configuration_set', 'maildun-production');

    $team = Team::factory()->create(['email_from_address' => 'campaigns@example.com']);
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'sespasswordXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'configuration_set' => 'workspace-configuration',
            'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-events',
        ],
    ]);

    $transport = app(TeamMailer::class)->resolve($team->fresh());

    // ConfigurationSetName is what makes SES publish to SNS at all; losing it
    // to the platform value would silently send a campaign nothing reports on.
    expect($transport->configuration['options'])
        ->toBe(['ConfigurationSetName' => 'workspace-configuration'])
        ->and($transport->configuration['transport'])->toBe('ses-v2')
        ->and($transport->sesConfigurationSet)->toBe('workspace-configuration');
});

test('smtp messages never include ses feedback metadata', function () {
    config()->set('services.ses.configuration_set', 'maildun-production');
    $delivery = EmailDelivery::factory()->create(['provider' => EmailProvider::Smtp]);
    $attempt = EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->create();

    $mailable = new CampaignEmail($delivery, '<p>Tracked</p>', $attempt);

    expect($mailable->envelope()->metadata)->toBe([])
        ->and($mailable->headers()->text)->not->toHaveKeys([
            'X-SES-CONFIGURATION-SET',
            'X-SES-MESSAGE-TAGS',
        ]);
});

test('a queued campaign delivery uses and reports the current workspace provider', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'sespasswordXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'configuration_set' => 'workspace-configuration',
            'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-events',
        ],
    ]);
    $email = Email::factory()->for($team)->create([
        'html' => '<p>Hello</p>',
        'status' => EmailStatus::Queued,
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create([
        'provider' => 'smtp',
        'uses_team_email_integration' => false,
    ]);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail): bool => $mail->usesMailer(TeamMailer::MAILER_NAME));
    $attempt = $delivery->attempts()->sole();

    expect($delivery->fresh()->provider)->toBe(EmailProvider::AmazonSes->value)
        ->and($delivery->fresh()->uses_team_email_integration)->toBeTrue()
        ->and($attempt->team_email_integration_id)->toBe($integration->id)
        ->and($attempt->integration_uuid)->toBe($integration->uuid)
        ->and($attempt->integration_name)->toBe($integration->name)
        ->and($attempt->ses_configuration_set)->toBe('workspace-configuration')
        ->and($attempt->ses_sns_topic_arn_hash)->toBe(hash(
            'sha256',
            'arn:aws:sns:us-east-1:123456789012:maildun-events',
        ));
});

test('a claimed delivery resolves one transport and does not regress feedback received during acceptance', function () {
    $email = Email::factory()->create(['html' => '<p>Hello</p>', 'status' => EmailStatus::Queued]);
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    $transport = new ResolvedEmailTransport(
        provider: EmailProvider::Smtp,
        integrationId: null,
        integrationUuid: null,
        integrationName: null,
        configuration: null,
        sesConfigurationSet: null,
        sesSnsTopicArnHash: null,
    );
    $teamMailer = Mockery::mock(TeamMailer::class);
    $teamMailer->shouldReceive('resolve')->once()->andReturn($transport);
    $teamMailer->shouldReceive('sendResolved')
        ->once()
        ->withArgs(function (
            ResolvedEmailTransport $resolvedTransport,
            string $recipient,
            CampaignEmail $mail,
        ) use ($transport): bool {
            $mail->attempt?->forceFill([
                'status' => EmailDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ])->save();
            $mail->delivery->forceFill([
                'status' => EmailDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ])->save();

            return $resolvedTransport === $transport && $recipient === 'reader@example.com';
        })
        ->andReturnNull();
    $delivery->forceFill(['email_address' => 'reader@example.com'])->save();

    (new SendEmailDelivery($delivery->id))->handle(
        app(BuildTrackedEmailHtml::class),
        $teamMailer,
    );

    expect($delivery->attempts()->sole()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($delivery->attempts()->sole()->sent_at)->not->toBeNull()
        ->and($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Delivered)
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
});

test('legacy workspace providers fail closed before mail is sent', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->create([
        'provider' => EmailProvider::Resend,
        'name' => 'Legacy Resend',
        'settings' => ['api_key' => 'legacy-key'],
    ]);

    expect(app(TeamMailer::class)->hasIntegration($team))->toBeFalse()
        ->and(fn () => app(TeamMailer::class)->sendWithIntegration(
            $team,
            $integration,
            'reader@example.com',
            new CampaignEmail(EmailDelivery::factory()->create(), '<p>Hello</p>'),
            'delivery@example.com',
        ))->toThrow(EmailTransportException::class);

    Mail::assertNothingSent();
});

test('ses workspace providers require a configuration set and feedback topic', function () {
    $team = Team::factory()->create();
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create([
        'settings' => [
            'region' => 'us-east-1',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'sespasswordXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ],
    ]);

    expect(app(TeamMailer::class)->hasIntegration($team))->toBeFalse()
        ->and(fn () => app(TeamMailer::class)->resolve($team))->toThrow(EmailTransportException::class);
});

test('failed and delayed deliveries can be retried without touching bounces', function () {
    Bus::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 3,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $failed = $email->deliveries()->create([
        'email_address' => 'fail@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'provider' => 'smtp',
        'failure_reason' => 'Connection timed out',
    ]);
    $delayed = $email->deliveries()->create([
        'email_address' => 'later@example.com',
        'status' => EmailDeliveryStatus::Delayed,
        'provider' => 'ses',
        'delayed_at' => now(),
        'failure_reason' => 'MailboxFull',
    ]);
    $bounced = $email->deliveries()->create([
        'email_address' => 'gone@example.com',
        'status' => EmailDeliveryStatus::Bounced,
        'provider' => 'ses',
        'bounced_at' => now(),
        'failure_reason' => 'General',
    ]);

    $this->actingAs($user)
        ->from(route('emails.show', [$user->currentTeam, $email]))
        ->post(route('emails.retry', [$user->currentTeam, $email]))
        ->assertRedirect(route('emails.show', [$user->currentTeam, $email]));

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
        && $batch->jobs->every(fn (object $job): bool => $job instanceof SendEmailDelivery));

    expect($failed->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($failed->fresh()->failure_reason)->toBeNull()
        ->and($delayed->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($bounced->fresh()->status)->toBe(EmailDeliveryStatus::Bounced)
        ->and($email->fresh()->status)->toBe(EmailStatus::Sending);
});

test('a retry leaves deliveries to suppressed addresses alone', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
    ]);
    $failed = EmailDelivery::factory()->for($email)->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Failed,
    ]);
    $suppressed = EmailDelivery::factory()->for($email)->create([
        'email_address' => 'bounced@example.com',
        'status' => EmailDeliveryStatus::Failed,
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);

    app(RetryEmailDeliveries::class)->handle($email);

    expect($failed->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($suppressed->fresh()->status)->toBe(EmailDeliveryStatus::Failed);
});

test('a retry leaves deliveries to subscribers pending confirmation alone', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => true]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
    ]);
    $confirmed = EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => Subscriber::factory()->for($audience)->create()->id,
        'status' => EmailDeliveryStatus::Failed,
    ]);
    $pending = EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => Subscriber::factory()->for($audience)->pendingConfirmation()->create()->id,
        'status' => EmailDeliveryStatus::Failed,
    ]);

    app(RetryEmailDeliveries::class)->handle($email);

    expect($confirmed->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($pending->fresh()->status)->toBe(EmailDeliveryStatus::Failed);
});

test('a bulk retry leaves unconfirmed deliveries alone unless asked to include them', function (bool $includeUnconfirmed, EmailDeliveryStatus $unconfirmedStatus) {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
    ]);
    $refused = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => null,
    ]);
    $unconfirmed = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('emails.show', [$team, $email]))
        ->post(route('emails.retry', [$team, $email]), ['include_unconfirmed' => $includeUnconfirmed])
        ->assertRedirect(route('emails.show', [$team, $email]));

    expect($refused->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($unconfirmed->fresh()->status)->toBe($unconfirmedStatus);
})->with([
    'by default' => [false, EmailDeliveryStatus::Failed],
    'when included' => [true, EmailDeliveryStatus::Queued],
]);

test('retrying a single unconfirmed delivery requires confirmation', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Failed,
        'recipient_count' => 1,
    ]);
    $unconfirmed = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('emails.deliveries.retry', [$team, $email, $unconfirmed]))
        ->assertSessionHasErrors([
            'email' => 'These deliveries may already have reached their recipients. Confirm that you want to send them again.',
        ]);

    Bus::assertNothingBatched();

    expect($unconfirmed->fresh()->status)->toBe(EmailDeliveryStatus::Failed);
});

test('a confirmed retry queues a single unconfirmed delivery again', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Failed,
        'recipient_count' => 1,
    ]);
    $unconfirmed = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('emails.deliveries.retry', [$team, $email, $unconfirmed]), ['include_unconfirmed' => true])
        ->assertSessionHasNoErrors();

    expect($unconfirmed->fresh()->status)->toBe(EmailDeliveryStatus::Queued);
});

test('the campaign report flags unconfirmed deliveries', function () {
    $this->freezeTime();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'refused@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => null,
    ]);
    $this->travel(1)->minute();
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'unconfirmed@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('emails.recipients', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.retryable', 2)
            ->where('metrics.unconfirmed', 1)
            ->where('recipients.data.0.email', 'unconfirmed@example.com')
            ->where('recipients.data.0.unconfirmed', true)
            ->where('recipients.data.1.email', 'refused@example.com')
            ->where('recipients.data.1.unconfirmed', false));
});

test('each recipient row says whether it can be retried and why not', function (
    EmailStatus $campaignStatus,
    EmailDeliveryStatus $deliveryStatus,
    SubscriberStatus $subscriberStatus,
    bool $suppressed,
    bool $canRetry,
    ?string $blockedReason,
) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'reader@example.com',
        'status' => $subscriberStatus,
    ]);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'status' => $campaignStatus,
        'recipient_count' => 1,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $subscriber->id,
        'email_address' => 'reader@example.com',
        'status' => $deliveryStatus,
    ]);

    if ($suppressed) {
        EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'reader@example.com']);
    }

    $this->actingAs($user)
        ->get(route('emails.recipients', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recipients.data.0.can_retry', $canRetry)
            ->where('recipients.data.0.retry_blocked_reason', $blockedReason));
})->with([
    'a failed send' => [EmailStatus::PartiallyFailed, EmailDeliveryStatus::Failed, SubscriberStatus::Subscribed, false, true, null],
    'a campaign still sending' => [EmailStatus::Sending, EmailDeliveryStatus::Failed, SubscriberStatus::Subscribed, false, false, 'Wait until this campaign finishes sending.'],
    'an unsubscribed recipient' => [EmailStatus::PartiallyFailed, EmailDeliveryStatus::Failed, SubscriberStatus::Unsubscribed, false, false, 'This recipient has unsubscribed.'],
    'a suppressed address' => [EmailStatus::PartiallyFailed, EmailDeliveryStatus::Failed, SubscriberStatus::Subscribed, true, false, 'This address is suppressed after a permanent bounce or spam complaint.'],
    'a permanent bounce' => [EmailStatus::Sent, EmailDeliveryStatus::Bounced, SubscriberStatus::Subscribed, false, false, 'Permanent bounces are never retried.'],
    'a spam complaint' => [EmailStatus::Sent, EmailDeliveryStatus::Complained, SubscriberStatus::Subscribed, false, false, 'Spam complaints are never retried.'],
    'a delivered message' => [EmailStatus::Sent, EmailDeliveryStatus::Delivered, SubscriberStatus::Subscribed, false, false, null],
]);

test('the retryable filter lists exactly the deliveries the retry count covers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $reader = Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    $gone = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'gone@example.com']);
    $bounced = Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 3,
    ]);

    foreach ([$reader, $gone, $bounced] as $subscriber) {
        EmailDelivery::factory()->for($email)->create([
            'subscriber_id' => $subscriber->id,
            'email_address' => $subscriber->email,
            'status' => EmailDeliveryStatus::Failed,
        ]);
    }

    $this->actingAs($user)
        ->get(route('emails.recipients', [$team, $email, 'status' => 'retryable']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.retryable', 1)
            ->has('recipients.data', 1)
            ->where('recipients.data.0.email', 'reader@example.com'));
});

test('the campaign report does not count suppressed addresses as retryable', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Failed,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'bounced@example.com',
        'status' => EmailDeliveryStatus::Failed,
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.failed', 2)
            ->where('metrics.retryable', 1));
});

test('a sending campaign reports deliveries waiting on an automatic retry', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 3,
        'send_started_at' => now(),
    ]);
    $retrying = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    EmailDeliveryAttempt::factory()->for($retrying, 'delivery')->create(['status' => EmailDeliveryStatus::Failed]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Failed]);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.retrying', 1)
            ->where('metrics.failed', 1));
});

test('a sending campaign with no finished delivery for several minutes is stalled', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 2,
        'send_started_at' => now()->subMinutes(20),
    ]);
    EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Sent,
        'updated_at' => now()->subMinutes(12),
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.stalled', true)
            ->where('metrics.worker_state', 'unknown')
            ->where('metrics.eta_seconds', null));
});

test('a sending campaign estimates the time left from its recent pace', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Sending,
        'recipient_count' => 20,
        'send_started_at' => now()->subMinutes(5),
    ]);
    EmailDelivery::factory()->count(10)->for($email)->create([
        'status' => EmailDeliveryStatus::Sent,
        'updated_at' => now()->subMinute(),
    ]);
    EmailDelivery::factory()->count(10)->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.stalled', false)
            ->where('metrics.eta_seconds', 300));
});

test('a finished campaign reports no in-flight progress', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
        'sent_at' => now(),
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.stalled', false)
            ->where('metrics.worker_state', null));
});

test('a delivery that fails for good records why as a failure code', function (Throwable $exception, EmailFailureCode $code) {
    $delivery = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Sending]);

    (new SendEmailDelivery($delivery->id))->failed($exception);

    expect($delivery->fresh())
        ->status->toBe(EmailDeliveryStatus::Failed)
        ->failure_code->toBe($code);
})->with([
    'no tested provider' => [fn () => EmailTransportException::providerUnavailable(), EmailFailureCode::ProviderUnavailable],
    'unverified sender' => [fn () => EmailTransportException::unauthorizedSender(), EmailFailureCode::SenderUnauthorized],
    'provider refused' => [fn () => new EmailTransportException, EmailFailureCode::ProviderRefused],
    'anything else' => [fn () => new RuntimeException('boom'), EmailFailureCode::Unknown],
]);

test('the report groups failed deliveries by cause, most common first', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 5,
    ]);
    EmailDelivery::factory()->count(2)->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'failure_code' => EmailFailureCode::ProviderRefused,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Failed,
        'failure_code' => null,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Delayed,
        'failure_code' => EmailFailureCode::TransientBounce,
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('failureCauses', 3)
            ->where('failureCauses.0.code', 'provider_refused')
            ->where('failureCauses.0.count', 2)
            ->where('failureCauses.0.label', 'The provider refused the message'));
});

test('finalizing a campaign codes deliveries that never reported back', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'recipient_count' => 1]);
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    (new FinalizeEmailSend($email->id))();

    expect($delivery->fresh()->failure_code)->toBe(EmailFailureCode::NoReport);
});

test('recipients a failed loader never reached can be queued without resending the rest', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    [$reached, $missedA, $missedB] = Subscriber::factory()->for($audience)->count(3)->create()->sortBy('id')->values()->all();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
        'status' => EmailStatus::Failed,
        'recipient_count' => 3,
        'send_started_at' => now()->subHour(),
        'sent_at' => now()->subHour(),
    ]);
    $sent = EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $reached->id,
        'email_address' => $reached->email,
        'status' => EmailDeliveryStatus::Sent,
    ]);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.unqueued', 2));

    $this->actingAs($user)
        ->post(route('emails.queue-remaining', [$team, $email]))
        ->assertRedirect();

    $run = $email->sendRuns()->sole();
    expect($run->kind)->toBe(EmailSendRunKind::Resume)
        ->and($run->recipient_count)->toBe(2)
        ->and($email->fresh()->status)->toBe(EmailStatus::Queued);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->first() instanceof PrepareEmailSendChunk
        && $batch->jobs->first()->afterSubscriberId === $reached->id);

    [$loader] = (new PrepareEmailSendChunk($email->id, $reached->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($email->deliveries()->whereIn('subscriber_id', [$missedA->id, $missedB->id])->pluck('email_send_run_id')->unique()->all())->toBe([$run->id])
        ->and($sent->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($email->deliveries()->count())->toBe(3);
});

test('a fully prepared campaign has no remaining recipients to queue', function () {
    Bus::fake();

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    $this->actingAs($user)
        ->post(route('emails.queue-remaining', [$user->currentTeam, $email]))
        ->assertInvalid(['email' => 'Every recipient of this campaign has already been queued.']);

    Bus::assertNothingBatched();
});

test('a single failed delivery can be retried', function () {
    Bus::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 2,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $failed = $email->deliveries()->create([
        'email_address' => 'fail@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'provider' => 'smtp',
        'failure_reason' => 'Connection timed out',
    ]);
    $email->deliveries()->create([
        'email_address' => 'ok@example.com',
        'status' => EmailDeliveryStatus::Sent,
        'provider' => 'smtp',
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('emails.show', [$user->currentTeam, $email]))
        ->post(route('emails.deliveries.retry', [$user->currentTeam, $email, $failed]))
        ->assertRedirect(route('emails.show', [$user->currentTeam, $email]));

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    expect($failed->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($email->fresh()->status)->toBe(EmailStatus::Sending);
});

test('a retry is recorded as its own send run with its own progress', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 3,
        'send_started_at' => now()->subHour(),
        'sent_at' => now()->subHour(),
    ]);
    $firstRun = EmailSendRun::factory()->for($email)->finished()->create(['recipient_count' => 3]);
    $failed = EmailDelivery::factory()->for($email)->create([
        'email_send_run_id' => $firstRun->id,
        'status' => EmailDeliveryStatus::Failed,
    ]);
    EmailDelivery::factory()->count(2)->for($email)->create([
        'email_send_run_id' => $firstRun->id,
        'status' => EmailDeliveryStatus::Sent,
    ]);

    app(RetryEmailDeliveries::class)->handle($email);

    $retryRun = $email->sendRuns()->latest('id')->first();

    expect($retryRun->kind)->toBe(EmailSendRunKind::Retry)
        ->and($retryRun->recipient_count)->toBe(1)
        ->and($retryRun->finished_at)->toBeNull()
        ->and($failed->fresh()->email_send_run_id)->toBe($retryRun->id);

    $this->actingAs($user)
        ->get(route('emails.show', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.run_kind', 'retry')
            ->where('metrics.run_recipient_count', 1)
            ->where('metrics.run_processed', 0)
            ->has('sendRuns', 2)
            ->where('sendRuns.0.kind', 'retry')
            ->where('sendRuns.1.kind', 'initial')
            ->where('sendRuns.1.processed', 2));

    (new FinalizeEmailSend($email->id))();

    expect($retryRun->fresh()->finished_at)->not->toBeNull();
});

test('the first send records an initial run and stamps its deliveries', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->count(2)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)->post(route('emails.send', [$team, $email]));

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    $run = $email->sendRuns()->sole();

    expect($run->kind)->toBe(EmailSendRunKind::Initial)
        ->and($run->recipient_count)->toBe(2)
        ->and($email->deliveries()->where('email_send_run_id', $run->id)->count())->toBe(2);
});

test('permanent bounces cannot be retried', function () {
    Bus::fake();

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $bounced = $email->deliveries()->create([
        'email_address' => 'gone@example.com',
        'status' => EmailDeliveryStatus::Bounced,
        'provider' => 'ses',
        'bounced_at' => now(),
        'failure_reason' => 'General',
    ]);

    $this->actingAs($user)
        ->from(route('emails.show', [$user->currentTeam, $email]))
        ->post(route('emails.deliveries.retry', [$user->currentTeam, $email, $bounced]))
        ->assertSessionHasErrors('email');

    Bus::assertNothingBatched();
    expect($bounced->fresh()->status)->toBe(EmailDeliveryStatus::Bounced);
});

test('members cannot retry failed deliveries', function () {
    Bus::fake();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = Email::factory()->for($team)->create([
        'status' => EmailStatus::Failed,
        'recipient_count' => 1,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'fail@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'provider' => 'smtp',
    ]);

    $this->actingAs($member)
        ->post(route('emails.retry', [$team, $email]))
        ->assertForbidden();

    Bus::assertNothingBatched();
});

test('the campaign report can filter bounced recipients', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'ok@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => 'ses',
        'delivered_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'gone@example.com',
        'status' => EmailDeliveryStatus::Bounced,
        'provider' => 'ses',
        'bounced_at' => now(),
        'failure_reason' => 'General',
    ]);

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email, 'status' => 'bounced']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/recipients')
            ->where('filters.status', 'bounced')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.email', 'gone@example.com')
            ->where('metrics.bounced', 1));
});

test('the campaign report can filter opened and clicked recipients', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 3,
        'send_started_at' => now()->subMinute(),
        'sent_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'opened@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => 'ses',
        'delivered_at' => now(),
        'opens_count' => 1,
        'first_opened_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'clicked@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => 'ses',
        'delivered_at' => now(),
        'opens_count' => 1,
        'clicks_count' => 2,
        'first_opened_at' => now(),
        'first_clicked_at' => now(),
    ]);
    $email->deliveries()->create([
        'email_address' => 'quiet@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => 'ses',
        'delivered_at' => now(),
    ]);
    app(RebuildEmailTrackingAggregates::class)->handle($email);

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email, 'status' => 'opened']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/recipients')
            ->where('filters.status', 'opened')
            ->has('recipients.data', 2)
            ->where('metrics.opened', 2));

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email, 'status' => 'clicked']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'clicked')
            ->has('recipients.data', 1)
            ->where('recipients.data.0.email', 'clicked@example.com')
            ->where('metrics.clicked', 1));
});

test('a retried job never sends the same delivery twice', function () {
    Mail::fake();

    $email = Email::factory()->create(['html' => '<p>Hello</p>', 'status' => EmailStatus::Queued]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    $job = new SendEmailDelivery($delivery->id);
    $job->handle(app(BuildTrackedEmailHtml::class), app(TeamMailer::class));
    $job->handle(app(BuildTrackedEmailHtml::class), app(TeamMailer::class));

    Mail::assertSentCount(1);

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->fresh()->send_attempted_at)->not->toBeNull()
        ->and($delivery->attempts()->count())->toBe(1)
        ->and($delivery->attempts()->sole()->status)->toBe(EmailDeliveryStatus::Sent);
});

test('the first claimed delivery moves a queued campaign to sending', function () {
    Mail::fake();

    $email = Email::factory()->create(['html' => '<p>Hello</p>', 'status' => EmailStatus::Queued]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    (new SendEmailDelivery($delivery->id))->handle(app(BuildTrackedEmailHtml::class), app(TeamMailer::class));

    Mail::assertSentCount(1);

    expect($email->fresh()->status)->toBe(EmailStatus::Sending);
});

test('a late delivery job does not reopen a finished campaign', function (EmailStatus $finishedStatus) {
    Mail::fake();

    $email = Email::factory()->create([
        'html' => '<p>Hello</p>',
        'status' => $finishedStatus,
        'sent_at' => now(),
    ]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $delivery = EmailDelivery::factory()->for($email)->create([
        'status' => EmailDeliveryStatus::Sent,
        'send_attempted_at' => now(),
    ]);

    (new SendEmailDelivery($delivery->id))->handle(app(BuildTrackedEmailHtml::class), app(TeamMailer::class));

    Mail::assertNothingSent();

    expect($email->fresh()->status)->toBe($finishedStatus);
})->with([EmailStatus::Sent, EmailStatus::PartiallyFailed, EmailStatus::Failed]);

test('a transport failure hands the delivery back so the queue can retry it', function () {
    $email = Email::factory()->create(['html' => '<p>Hello</p>', 'status' => EmailStatus::Queued]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $delivery = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Queued]);

    $teamMailer = Mockery::mock(TeamMailer::class);
    $teamMailer->shouldReceive('resolve')
        ->once()
        ->andReturn(app(TeamMailer::class)->resolve($email->team));
    $teamMailer->shouldReceive('sendResolved')
        ->once()
        ->andThrow(new RuntimeException('The transport refused it.'));

    expect(fn () => (new SendEmailDelivery($delivery->id))->handle(
        app(BuildTrackedEmailHtml::class),
        $teamMailer,
    ))
        ->toThrow(RuntimeException::class);

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($delivery->fresh()->send_attempted_at)->toBeNull()
        ->and($delivery->attempts()->sole()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($delivery->attempts()->sole()->failure_reason)->toBe(__('Delivery failed.'));
});

test('a deliberate retry re-arms a delivery that already went out', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 1,
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $subscriber->id,
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now(),
    ]);
    $oldAttempt = EmailDeliveryAttempt::factory()->for($delivery, 'delivery')->create([
        'status' => EmailDeliveryStatus::Failed,
        'provider_message_id' => 'old-provider-message',
        'failure_reason' => 'Old failure',
    ]);

    app(RetryEmailDeliveries::class)->handle($email, includeUnconfirmed: true);

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($delivery->fresh()->send_attempted_at)->toBeNull()
        ->and($delivery->attempts()->count())->toBe(1)
        ->and($oldAttempt->fresh()->provider_message_id)->toBe('old-provider-message')
        ->and($oldAttempt->fresh()->failure_reason)->toBe('Old failure')
        ->and($oldAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Failed);
});

test('finishing a campaign fails deliveries that never reported back', function () {
    $email = Email::factory()->create(['status' => EmailStatus::Sending, 'recipient_count' => 2]);
    $stuck = EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sending]);
    $stuckAttempt = EmailDeliveryAttempt::factory()->for($stuck, 'delivery')->create([
        'status' => EmailDeliveryStatus::Sending,
    ]);
    EmailDelivery::factory()->for($email)->create(['status' => EmailDeliveryStatus::Sent]);

    (new FinalizeEmailSend($email->id))(Mockery::mock(Batch::class));

    expect($stuck->fresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($stuck->fresh()->failure_reason)->not->toBeNull()
        ->and($stuckAttempt->fresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($stuckAttempt->fresh()->failure_reason)->toBe($stuck->fresh()->failure_reason)
        ->and($email->fresh()->status)->toBe(EmailStatus::PartiallyFailed);
});
