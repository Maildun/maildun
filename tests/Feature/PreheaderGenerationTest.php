<?php

use App\Ai\Agents\PreheaderWriter;
use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\TransactionalEmail;
use App\Models\User;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'test-key', 'ai.providers.anthropic.key' => null, 'ai.providers.gemini.key' => null]);
});

function preheaderDraft(?User $user = null): Email
{
    $user ??= User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);

    return Email::factory()->for($user->currentTeam)->create([
        'editor' => EmailEditor::Builder,
        'preheader' => 'Old preview text',
        'design' => EmailTemplate::builderDesign([
            'block-1' => ['type' => 'text', 'props' => ['markdown' => 'Our autumn collection has three new jackets.']],
        ]),
    ]);
}

/** @return array<string, mixed> */
function preheaderPayload(Email|EmailTemplate|TransactionalEmail $record, array $overrides = []): array
{
    $design = $record->design;
    $design['settings']['preheader'] = 'Old preview text';

    return ['design' => $design, 'subject' => "Autumn\nis here", ...$overrides];
}

test('writes a preheader from the subject and the email text without saving it', function () {
    $user = User::factory()->create();
    $email = preheaderDraft($user);
    $before = $email->refresh()->getAttributes();
    PreheaderWriter::fake([['preheader' => "  Three new jackets,\n just in time.  "]]);

    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email))
        ->assertOk()
        ->assertExactJson(['preheader' => 'Three new jackets, just in time.']);

    PreheaderWriter::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Subject: Autumn is here')
        && str_contains($prompt->prompt, 'Our autumn collection has three new jackets.')
        && ! str_contains($prompt->prompt, 'Old preview text'));
    expect($email->refresh()->getAttributes())->toBe($before);
});

test('works in the transactional and template editors', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $transactional = TransactionalEmail::factory()->for($team)->builder()->create();
    $template = EmailTemplate::factory()->for($team)->builder()->create();
    PreheaderWriter::fake([['preheader' => 'Your receipt is ready.'], ['preheader' => 'A fresh start.']]);

    $this->actingAs($user)
        ->postJson(route('transactional_emails.preheader', [$team, $transactional]), preheaderPayload($transactional))
        ->assertOk()
        ->assertJsonPath('preheader', 'Your receipt is ready.');
    $this->actingAs($user)
        ->postJson(route('email_templates.preheader', [$team, $template]), preheaderPayload($template))
        ->assertOk()
        ->assertJsonPath('preheader', 'A fresh start.');
});

test('asks for content before writing a preheader for an empty email', function () {
    $user = User::factory()->create();
    $email = preheaderDraft($user);
    PreheaderWriter::fake();

    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email, [
            'design' => EmailTemplate::builderDesign([]),
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Add some content to the email first.');

    PreheaderWriter::assertNeverPrompted();
});

test('counts against the daily assistant allowance', function () {
    config(['assistant.daily_limit' => 1]);
    $user = User::factory()->create();
    $email = preheaderDraft($user);
    PreheaderWriter::fake([['preheader' => 'First.'], ['preheader' => 'Second.']]);

    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email))
        ->assertOk();
    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email))
        ->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'used its design assistant requests for today'));

    PreheaderWriter::assertPromptedTimes(1);
});

test('explains a provider failure without exposing it', function () {
    $user = User::factory()->create();
    $email = preheaderDraft($user);
    PreheaderWriter::fake(fn () => throw ProviderConnectionException::forProvider('openai', 0, new RuntimeException('cURL error 28')));

    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email))
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'could not reach the AI provider')
            && ! str_contains($message, 'cURL'));
});

test('only editors of a draft can generate a preheader', function () {
    $owner = User::factory()->create();
    $email = preheaderDraft($owner);
    $viewer = User::factory()->create();
    $owner->currentTeam->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($owner->currentTeam, $viewer);
    PreheaderWriter::fake();

    $this->actingAs($viewer)
        ->postJson(route('emails.preheader', [$owner->currentTeam, $email]), preheaderPayload($email))
        ->assertForbidden();

    $email->update(['status' => EmailStatus::Sent]);
    $this->actingAs($owner)
        ->postJson(route('emails.preheader', [$owner->currentTeam, $email]), preheaderPayload($email))
        ->assertConflict();

    PreheaderWriter::assertNeverPrompted();
});

test('asks to try again when the writer returns a blank preheader', function () {
    $user = User::factory()->create();
    $email = preheaderDraft($user);
    PreheaderWriter::fake([['preheader' => "  \n "]]);

    $this->actingAs($user)
        ->postJson(route('emails.preheader', [$user->currentTeam, $email]), preheaderPayload($email))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The assistant could not write a preheader. Try again.');

    expect($email->refresh()->preheader)->toBe('Old preview text');
});
