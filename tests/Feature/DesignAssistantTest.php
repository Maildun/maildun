<?php

use App\Ai\Agents\DesignAssistant;
use App\Ai\AssistantFailure;
use App\Ai\DesignSession;
use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\EmailBuilderAgent;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'test-key', 'ai.providers.anthropic.key' => null, 'ai.providers.gemini.key' => null]);
});

function designAssistantDraft(?User $user = null): Email
{
    $user ??= User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);

    return Email::factory()->for($user->currentTeam)->builder()->create();
}

/** @return array<string, mixed> */
function designAssistantPayload(Email|EmailTemplate|TransactionalEmail $record, array $overrides = []): array
{
    return [
        'prompt' => 'Add a footer',
        'messages' => [],
        'design' => $record->design,
        'model' => 'openai',
        ...$overrides,
    ];
}

/**
 * @return list<array{event: string, data: mixed}>
 */
function designAssistantEvents(string $body): array
{
    preg_match_all('/^event: (\S+)\ndata: (.*)$/m', $body, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => ['event' => $match[1], 'data' => json_decode($match[2], true)], $matches);
}

test('streams the reply and a replayable proposal without saving the email', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    $before = $email->refresh()->getAttributes();
    DesignAssistant::fake([
        new ToolCall('call_footer', 'insert_section', ['name' => 'footer']),
        'Added a footer with the unsubscribe link.',
    ]);

    $response = $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertOk();
    $events = designAssistantEvents($response->streamedContent());

    expect(collect($events)->firstWhere('event', 'tool')['data'])->toBe(['name' => 'insert_section', 'detail' => 'footer']);
    expect(collect($events)->pluck('event')->all())->toContain('text')
        ->and(end($events)['event'])->toBe('done');

    $proposal = collect($events)->firstWhere('event', 'proposal')['data'];
    $replayed = app(EmailBuilderAgent::class)->run($email->design, null, 'apply_ops', ['ops' => $proposal['ops']]);

    expect($replayed['html'])->toContain('{{ unsubscribe_url }}')
        ->and(collect($proposal['warnings'])->pluck('code'))->not->toContain('missing-unsubscribe')
        ->and($email->refresh()->getAttributes())->toBe($before);
    DesignAssistant::assertPrompted('Add a footer');
});

test('omits the proposal when the assistant only answers', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Which colors should I use?']);

    $response = $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertOk();

    expect(collect(designAssistantEvents($response->streamedContent()))->pluck('event'))->not->toContain('proposal');
});

test('works on block email templates', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::factory()->for($user->currentTeam)->builder()->create();
    DesignAssistant::fake(['Done.']);

    $this->actingAs($user)
        ->post(route('email_templates.design-assistant', [$user->currentTeam, $template]), designAssistantPayload($template))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPrompted('Add a footer');
});

test('briefs the assistant with the campaign subject, preview text, and audience', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Done.']);

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email, [
            'context' => [
                'name' => 'Spring launch',
                'subject' => "Spring\ncollection",
                'preheader' => 'Three new colors',
                'audience' => 'Customers',
            ],
        ]))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'Name: Spring launch')
            && str_contains($instructions, 'Subject: Spring collection')
            && str_contains($instructions, 'Preview text: Three new colors')
            && str_contains($instructions, 'Audience: Customers')
            && ! str_contains($instructions, "Spring\ncollection");
    });
});

test('does not add a campaign brief when every campaign field is blank', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Done.']);

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email, [
            'context' => [
                'name' => '',
                'subject' => " \n ",
                'preheader' => '',
                'audience' => null,
            ],
        ]))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains((string) $prompt->agent->instructions(), '## This campaign'));
});

test('does not brief a template with campaign fields posted beside it', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::factory()->for($user->currentTeam)->builder()->create();
    DesignAssistant::fake(['Done.']);

    $this->actingAs($user)
        ->post(route('email_templates.design-assistant', [$user->currentTeam, $template]), designAssistantPayload($template, [
            'context' => ['subject' => 'ZzTemplateBriefShouldStayOut'],
        ]))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains((string) $prompt->agent->instructions(), 'ZzTemplateBriefShouldStayOut'));
});

test('rejects a campaign brief field longer than 255 characters', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake();

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email, [
            'context' => ['subject' => str_repeat('a', 256)],
        ]))
        ->assertInvalid(['context.subject']);

    DesignAssistant::assertNeverPrompted();
});

test('stops a reply that runs past a design answer and keeps the assistant on topic', function () {
    config(['assistant.max_reply_characters' => 40]);
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake([str_repeat('Here is a long essay about something else entirely. ', 5)]);

    $response = $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email, ['prompt' => 'Write me an essay about Rome']))
        ->assertOk();
    $events = collect(designAssistantEvents($response->streamedContent()));
    $text = $events->where('event', 'text')->pluck('data.delta')->implode('');

    expect(mb_strlen($text))->toBeLessThanOrEqual(40)
        ->and($events->firstWhere('event', 'error')['data']['message'])->toContain('only helps with this email')
        ->and($events->pluck('event'))->not->toContain('proposal');
    DesignAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'Politely decline anything else'));
});

test('limits how many assistant requests a workspace makes per day', function () {
    config(['assistant.daily_limit' => 1]);
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Done.', 'Done again.']);

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertOk()
        ->streamedContent();

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'used its design assistant requests for today'));

    DesignAssistant::assertPromptedTimes(1);
});

test('treats a daily limit of zero as no limit', function () {
    config(['assistant.daily_limit' => 0]);
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Done.', 'Done again.']);

    foreach ([1, 2] as $attempt) {
        $this->actingAs($user)
            ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
            ->assertOk()
            ->streamedContent();
    }

    DesignAssistant::assertPromptedTimes(2);
});

test('explains assistant failures without exposing provider details', function (Closure $exception, string $expected) {
    expect(AssistantFailure::describe($exception()))->toContain($expected);
})->with([
    'missing builder runtime' => [fn (): Throwable => ValidationException::withMessages(['design' => 'The email builder runtime is unavailable. Check MCP_EMAIL_BUILDER_RUNTIME.']), 'runtime is unavailable'],
    'bad api key' => [fn (): Throwable => new RequestException(new ClientResponse(new Psr7Response(401, [], '{"error":{"code":"invalid_api_key","message":"Incorrect API key sk-secret"}}'))), 'rejected the API key'],
    'unknown model' => [fn (): Throwable => new RequestException(new ClientResponse(new Psr7Response(404, [], '{"error":{"code":"model_not_found"}}'))), 'model is not available'],
    'quota exhausted' => [fn (): Throwable => RateLimitedException::forProvider('openai', 429, new RequestException(new ClientResponse(new Psr7Response(429, [], '{"error":{"code":"insufficient_quota"}}')))), 'out of credit'],
    'network' => [fn (): Throwable => ProviderConnectionException::forProvider('openai', 0, new RuntimeException('cURL error 28')), 'could not reach the AI provider'],
    'anything else' => [fn (): Throwable => new RuntimeException('boom'), 'unavailable right now'],
]);

test('streams the specific reason when the email builder runtime is missing', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(['Done.']);
    Process::fake(fn () => Process::result(errorOutput: 'bun: command not found', exitCode: 127));

    $response = $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertOk();
    $error = collect(designAssistantEvents($response->streamedContent()))->firstWhere('event', 'error');

    expect($error['data']['message'])->toContain('runtime is unavailable')->not->toContain('bun: command not found');
});

test('reports a missing provider key and a broken runtime from the check command', function () {
    config(['ai.providers.openai.key' => null]);

    $this->artisan('assistant:check')
        ->expectsOutputToContain('No assistant model is available')
        ->assertFailed();

    config(['ai.providers.openai.key' => 'test-key']);
    Process::fake(fn () => Process::result(errorOutput: 'bun: command not found', exitCode: 127));

    $this->artisan('assistant:check')
        ->expectsOutputToContain('bun: command not found')
        ->assertFailed();
});

test('refuses people who cannot edit the email', function () {
    $owner = User::factory()->create();
    $email = designAssistantDraft($owner);
    $viewer = User::factory()->create();
    $owner->currentTeam->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($owner->currentTeam, $viewer);
    DesignAssistant::fake();

    $this->actingAs($viewer)
        ->post(route('emails.design-assistant', [$owner->currentTeam, $email]), designAssistantPayload($email))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->post(route('emails.design-assistant', [$owner->currentTeam, $email]), designAssistantPayload($email))
        ->assertForbidden();

    DesignAssistant::assertNeverPrompted();
});

test('follows the editor each email was composed in, whatever the workspace uses now', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Html]);
    $campaign = Email::factory()->for($team)->builder()->create();
    $transactional = TransactionalEmail::factory()->for($team)->builder()->create();
    DesignAssistant::fake(['Done.', 'Done.']);

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$team, $campaign]), designAssistantPayload($campaign))
        ->assertOk()
        ->streamedContent();
    $this->actingAs($user)
        ->post(route('transactional_emails.design-assistant', [$team, $transactional]), designAssistantPayload($transactional))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPromptedTimes(2);
});

test('refuses emails composed in another editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $campaign = Email::factory()->for($team)->builder()->create(['editor' => EmailEditor::Html]);
    $transactional = TransactionalEmail::factory()->for($team)->builder()->create(['editor' => EmailEditor::Html]);
    DesignAssistant::fake();

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$team, $campaign]), designAssistantPayload($campaign))
        ->assertNotFound();
    $this->actingAs($user)
        ->post(route('transactional_emails.design-assistant', [$team, $transactional]), designAssistantPayload($transactional))
        ->assertNotFound();

    DesignAssistant::assertNeverPrompted();
});

test('templates keep their own editor whatever the workspace uses', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Html]);
    $template = EmailTemplate::factory()->for($team)->builder()->create();
    DesignAssistant::fake(['Done.']);

    $this->actingAs($user)
        ->post(route('email_templates.design-assistant', [$team, $template]), designAssistantPayload($template))
        ->assertOk()
        ->streamedContent();

    DesignAssistant::assertPrompted('Add a footer');
});

test('refuses campaigns that are no longer drafts', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    $email->update(['status' => EmailStatus::Sent]);
    DesignAssistant::fake();

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertStatus(409);

    DesignAssistant::assertNeverPrompted();
});

test('only accepts models whose provider has a key', function (string $model) {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake();

    $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email, ['model' => $model]))
        ->assertInvalid('model');

    DesignAssistant::assertNeverPrompted();
})->with(['unknown model' => 'gpt-made-up', 'provider without a key' => 'anthropic-default']);

test('offers available models only to people who can edit', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);

    $this->actingAs($user)
        ->get(route('emails.edit', [$user->currentTeam, $email]))
        ->assertInertia(fn ($page) => $page
            ->where('assistantModels', [
                ['id' => 'openai', 'label' => 'OpenAI', 'model' => 'gpt-6-luna', 'provider' => 'openai'],
            ]));

    config(['ai.providers.openai.key' => null]);

    $this->actingAs($user)
        ->get(route('emails.edit', [$user->currentTeam, $email]))
        ->assertInertia(fn ($page) => $page->where('assistantModels', []));
});

test('the design session reports every invalid op and leaves the design unchanged', function () {
    $email = designAssistantDraft();
    $session = app()->makeWith(DesignSession::class, [
        'document' => $email->design,
        'team' => $email->team,
        'mergeTags' => [],
        'requireUnsubscribe' => true,
    ]);

    $result = $session->run('apply_ops', ['ops' => [
        ['op' => 'remove', 'id' => 'missing-one'],
        ['op' => 'remove', 'id' => 'missing-two'],
    ]]);

    expect($result)->toContain('missing-one')->toContain('missing-two')
        ->and($session->ops())->toBe([]);

    $session->run('update_block', ['id' => 'block-1', 'props' => ['markdown' => 'Edited']]);

    expect($session->ops())->toHaveCount(1)
        ->and($session->run('get_block', ['id' => 'block-1']))->toContain('Edited');
});

test('streams a readable error and still finishes when the provider times out', function () {
    $user = User::factory()->create();
    $email = designAssistantDraft($user);
    DesignAssistant::fake(fn () => throw ProviderConnectionException::forProvider('openai', 0, new RuntimeException('cURL error 28: Operation timed out')));

    $response = $this->actingAs($user)
        ->post(route('emails.design-assistant', [$user->currentTeam, $email]), designAssistantPayload($email))
        ->assertOk();
    $events = collect(designAssistantEvents($response->streamedContent()));

    expect($events->firstWhere('event', 'error')['data']['message'])
        ->toContain('could not reach the AI provider')
        ->not->toContain('cURL')
        ->and($events->pluck('event')->all())->not->toContain('proposal')
        ->and($events->last()['event'])->toBe('done');
});

test('streams the proposal for a block transactional email', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);
    $transactional = TransactionalEmail::factory()->for($user->currentTeam)->builder()->create();
    $before = $transactional->refresh()->getAttributes();
    DesignAssistant::fake([
        new ToolCall('call_footer', 'insert_section', ['name' => 'footer']),
        'Added a footer.',
    ]);

    $response = $this->actingAs($user)
        ->post(route('transactional_emails.design-assistant', [$user->currentTeam, $transactional]), designAssistantPayload($transactional))
        ->assertOk();
    $events = collect(designAssistantEvents($response->streamedContent()));

    expect($events->firstWhere('event', 'proposal')['data']['ops'])->not->toBeEmpty()
        ->and($transactional->refresh()->getAttributes())->toBe($before);
});
