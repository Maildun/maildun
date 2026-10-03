<?php

use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Support\EmailBuilderContext;
use App\Mcp\Tools\EmailBuilder\CheckEmailBuilderTool;
use App\Mcp\Tools\EmailBuilder\EditEmailBuilderTool;
use App\Mcp\Tools\EmailBuilder\GetEmailBuilderTool;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\EmailBuilderAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;

function emailBuilderArguments(Email|TransactionalEmail $email): array
{
    $email->refresh();

    return [
        'workspace' => $email->team->slug,
        'target' => $email instanceof Email ? 'campaign' : 'transactional_email',
        'uuid' => $email->uuid,
        'revision' => app(EmailBuilderContext::class)->revision($email),
    ];
}

test('reads the native agent reference and individual blocks without changing the email', function () {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('uuid', $email->uuid)
            ->where('revision', fn (string $revision): bool => strlen($revision) === 64)
            ->where('outline', fn (string $outline): bool => str_contains($outline, 'block-1 text'))
            ->where('instructions', fn (string $instructions): bool => str_contains($instructions, 'unsubscribe_url'))
            ->where('tools', fn (Collection $tools): bool => $tools->pluck('name')->contains('apply_ops'))
            ->etc());

    MaildunServer::tool(GetEmailBuilderTool::class, [...emailBuilderArguments($email), 'block_id' => 'block-1'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('result.data.type', 'text')->etc());

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('exposes native tool input schemas with empty objects instead of arrays', function () {
    $email = Email::factory()->builder()->create();

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()
        ->assertSee('"name":"get_document","description":')
        ->assertSee('"properties":{}')
        ->assertSee('"additionalProperties":{}')
        ->assertDontSee('"properties":[]')
        ->assertDontSee('"additionalProperties":[]');
});

test('saves native block edits and matching delivery HTML and plain text', function () {
    $email = Email::factory()->builder()->create();
    $revision = emailBuilderArguments($email)['revision'];

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'update_block',
        'input' => ['id' => 'block-1', 'props' => ['markdown' => 'Agent **edited** this email.']],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('saved', true)
        ->where('revision', fn (string $value): bool => $value !== $revision)
        ->where('ops.0.op', 'update')
        ->etc());

    expect($email->refresh())
        ->design->blocks->{'block-1'}->props->markdown->toBe('Agent **edited** this email.')
        ->html->toContain('<strong>edited</strong>')
        ->plain_text->toContain('Agent edited this email.');
});

test('keeps transactional merge variables in sync with agent changes', function () {
    $email = TransactionalEmail::factory()->builder()->published()->create();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'update_block',
        'input' => ['id' => 'block-1', 'props' => ['markdown' => 'Your order {{ order_number }} is ready.']],
    ])->assertOk();

    expect($email->refresh())
        ->html->toContain('{{ order_number }}')
        ->variables->toContain(['key' => 'order_number', 'example' => ''])
        ->published_at->not->toBeNull();
});

test('previews native changes without saving the proposal', function () {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'update_theme',
        'input' => ['colors' => ['primary' => '#ff0000']],
        'dry_run' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('saved', false)
        ->where('outline', fn (string $outline): bool => str_contains($outline, '#ff0000'))
        ->where('ops.0.op', 'updateTheme')
        ->etc());

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('rejects an entire batch when one native operation is invalid', function () {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'apply_ops',
        'input' => ['ops' => [
            ['op' => 'update', 'id' => 'block-1', 'props' => ['markdown' => 'Must not persist']],
            ['op' => 'remove', 'id' => 'missing-block'],
        ]],
    ])->assertHasErrors(['Rejected, nothing changed']);

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('inserts and rearranges nested blocks atomically while preserving existing content', function () {
    $email = Email::factory()->builder()->create();
    $original = $email->design['blocks']['block-1'];

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'apply_ops',
        'input' => ['ops' => [
            ['op' => 'insert', 'blocks' => [['id' => 'hero', 'type' => 'container', 'children' => [
                ['id' => 'hero-title', 'type' => 'heading', 'props' => ['text' => 'Welcome']],
            ]]]],
            ['op' => 'move', 'id' => 'block-1', 'parentId' => 'hero', 'index' => 1],
            ['op' => 'updateTheme', 'colors' => ['primary' => '#123456']],
        ]],
    ])->assertOk();

    expect($email->refresh()->design)
        ->root->toBe(['hero'])
        ->blocks->hero->children->toBe(['hero-title', 'block-1'])
        ->theme->colors->primary->toBe('#123456')
        ->and($email->design['blocks']['block-1'])->toMatchArray($original);

    expect($email->html)->toContain('Welcome');
});

test('opens legacy designs without saving and migrates them when edited', function () {
    $email = Email::factory()->builder()->create(['design' => [
        'root' => ['type' => 'EmailLayout', 'data' => ['childrenIds' => ['body']]],
        'body' => ['type' => 'Text', 'data' => ['props' => ['text' => 'Legacy content']]],
    ]]);

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))->assertOk();
    expect($email->refresh()->design['root']['type'])->toBe('EmailLayout');

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'update_block',
        'input' => ['id' => 'body', 'props' => ['markdown' => 'Migrated content']],
    ])->assertOk();

    expect($email->refresh())->design->version->toBe(1)->html->toContain('Migrated content');
});

test('preserves an existing HTML body when a builder email has no design', function () {
    $email = Email::factory()->builder()->create(['design' => null, 'html' => '<p>Existing body</p>']);

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'insert_blocks',
        'input' => ['blocks' => [['type' => 'text', 'props' => ['markdown' => 'New footer']]]],
    ])->assertOk();

    expect($email->refresh()->html)->toContain('Existing body')->toContain('New footer');
});

test('returns actionable errors for a broken document without persisting changes', function () {
    $email = Email::factory()->builder()->create(['design' => EmailTemplate::builderDesign([
        'broken' => ['type' => 'unknown', 'props' => []],
    ])]);
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email), 'tool' => 'remove_block', 'input' => ['id' => 'broken'],
    ])->assertHasErrors(['blocks.broken']);

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('rejects stale revisions even when saves share the same timestamp', function () {
    $this->freezeTime();
    $email = Email::factory()->builder()->create();
    $arguments = emailBuilderArguments($email);
    $email->update(['html' => '<p>A newer edit</p>']);

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...$arguments,
        'tool' => 'remove_block',
        'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['The email changed since it was read']);

    expect($email->refresh())->html->toBe('<p>A newer edit</p>')->design->root->toBe(['block-1']);
});

test('rechecks the revision after rendering before saving the email', function () {
    $email = Email::factory()->builder()->create();
    $arguments = emailBuilderArguments($email);
    $rendered = app(EmailBuilderAgent::class)->run($email->design, $email->html, 'update_block', [
        'id' => 'block-1', 'props' => ['markdown' => 'Agent proposal'],
    ]);
    Process::fake(function () use ($email, $rendered) {
        $email->update(['html' => '<p>Saved during rendering</p>']);

        return Process::result(output: json_encode($rendered, JSON_THROW_ON_ERROR));
    });

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...$arguments,
        'tool' => 'update_block',
        'input' => ['id' => 'block-1', 'props' => ['markdown' => 'Agent proposal']],
    ])->assertHasErrors(['The email changed since it was read']);

    expect($email->refresh()->html)->toBe('<p>Saved during rendering</p>');
});

test('rejects native edits to campaigns that are no longer drafts', function () {
    $email = Email::factory()->builder()->create(['status' => EmailStatus::Sent]);
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'remove_block',
        'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['Only draft campaigns can be updated']);

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('refuses cross-workspace builder access', function (string $tool) {
    $email = Email::factory()->builder()->create();
    $outsider = User::factory()->create();

    MaildunServer::actingAs($outsider)->tool($tool, [
        ...emailBuilderArguments($email), 'tool' => 'remove_block', 'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['workspace was not found']);
})->with([GetEmailBuilderTool::class, EditEmailBuilderTool::class, CheckEmailBuilderTool::class]);

test('permits viewers to inspect a design but rejects their writes', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $email = Email::factory()->for($team)->builder()->create();
    $before = $email->refresh()->getAttributes();

    MaildunServer::actingAs($viewer)->tool(GetEmailBuilderTool::class, emailBuilderArguments($email))->assertOk();
    MaildunServer::actingAs($viewer)->tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email), 'tool' => 'remove_block', 'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['unauthorized']);

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('does not convert source or HTML emails through the block tools', function (EmailEditor $editor) {
    $email = Email::factory()->create(['editor' => $editor, 'source' => 'Original source']);
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertHasErrors(['does not use the block builder']);

    expect($email->refresh()->getAttributes())->toBe($before);
})->with([EmailEditor::Html, EmailEditor::Markdown, EmailEditor::PlainText]);

test('checks and renders the email without saving it', function () {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();

    MaildunServer::tool(CheckEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->whereType('html', 'string')
        ->whereType('text', 'string')
        ->whereType('result.data.warnings', 'array')
        ->etc());

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('reports lint warnings at the top level of check and preview results', function () {
    $email = Email::factory()->builder()->create();

    MaildunServer::tool(CheckEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('warnings', fn (Collection $warnings): bool => $warnings->pluck('code')->contains('missing-preheader'))
        ->where('result.data.warnings', fn (Collection $warnings): bool => $warnings->pluck('code')->contains('missing-preheader'))
        ->etc());

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'insert_blocks',
        'input' => ['blocks' => [['id' => 'photo', 'type' => 'image', 'props' => ['src' => 'https://cdn.maildun.test/photo.png']]]],
        'dry_run' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('warnings', fn (Collection $warnings): bool => $warnings->contains(fn (array $warning): bool => $warning['code'] === 'missing-alt'
            && $warning['severity'] === 'warning'
            && $warning['blockId'] === 'photo'))
        ->etc());
});

test('reports a missing runtime without exposing process errors or changing the email', function () {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();
    Process::fake(fn () => Process::result(errorOutput: 'private deployment details', exitCode: 127));

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email), 'tool' => 'remove_block', 'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['runtime is unavailable'])->assertDontSee('private deployment details');

    expect($email->refresh()->getAttributes())->toBe($before);
});

test('does not expose another workspaces email through an accessible workspace', function () {
    $owner = User::factory()->create();
    $email = Email::factory()->builder()->create();

    MaildunServer::actingAs($owner)->tool(GetEmailBuilderTool::class, [
        ...emailBuilderArguments($email), 'workspace' => $owner->currentTeam->slug,
    ])->assertHasErrors(['not found in this workspace']);
});

test('exposes native builder tools through the authenticated remote MCP endpoint', function () {
    $owner = User::factory()->create();
    $email = Email::factory()->for($owner->currentTeam)->builder()->create();
    app()->instance(ResourceServer::class, Mockery::mock(ResourceServer::class));
    Passport::actingAs($owner, ['mcp:use']);

    $this->postJson('/mcp/maildun', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'edit-email-builder', 'arguments' => [
            ...emailBuilderArguments($email),
            'tool' => 'update_block',
            'input' => ['id' => 'block-1', 'props' => ['markdown' => 'Remote agent edit']],
        ]],
    ])->assertOk()->assertJsonPath('result.structuredContent.saved', true);

    expect($email->refresh()->html)->toContain('Remote agent edit');
});

test('includes declared transactional variables in native agent instructions', function () {
    $email = TransactionalEmail::factory()->builder()->create(['variables' => [
        ['key' => 'order_number', 'example' => '123'],
    ]]);

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('instructions', fn (string $instructions): bool => str_contains($instructions, 'order_number'))
        ->etc());
});

test('includes custom audience fields in campaign agent instructions', function () {
    $audience = Audience::factory()->create();
    AudienceAttribute::factory()->for($audience)->create(['key' => 'loyalty_points']);
    $email = Email::factory()->for($audience->team)->for($audience)->builder()->create();

    MaildunServer::tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('instructions', fn (string $instructions): bool => str_contains($instructions, 'loyalty_points'))
        ->etc());
});

test('rejects edits to unsubscribed workspaces when remote billing access is required', function () {
    config(['billing.enabled' => true]);
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $email = Email::factory()->for($team)->builder()->create();

    MaildunServer::actingAs($owner)->tool(GetEmailBuilderTool::class, emailBuilderArguments($email))
        ->assertHasErrors(['An active subscription is required']);
});

test('rejects a malformed runtime response without changing the email', function (string $output) {
    $email = Email::factory()->builder()->create();
    $before = $email->refresh()->getAttributes();
    Process::fake(fn () => Process::result(output: $output));

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email), 'tool' => 'remove_block', 'input' => ['id' => 'block-1'],
    ])->assertHasErrors(['invalid response']);

    expect($email->refresh()->getAttributes())->toBe($before);
})->with(['invalid JSON' => '{', 'missing output' => '{"ok":true}']);

test('renders native edits using a configured Node runtime', function () {
    config(['mcp.email_builder_runtime' => 'node']);
    $email = Email::factory()->builder()->create();

    MaildunServer::tool(EditEmailBuilderTool::class, [
        ...emailBuilderArguments($email),
        'tool' => 'insert_section',
        'input' => ['name' => 'hero'],
    ])->assertOk();

    expect($email->refresh()->design['root'])->toHaveCount(2);
});
