<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Audiences\CreateAudienceTool;
use App\Mcp\Tools\Audiences\DeleteAudienceTool;
use App\Mcp\Tools\Audiences\UpdateAudienceTool;
use App\Mcp\Tools\Campaigns\CreateCampaignTool;
use App\Mcp\Tools\Campaigns\DeleteCampaignTool;
use App\Mcp\Tools\Campaigns\UpdateCampaignTool;
use App\Mcp\Tools\TransactionalEmails\CreateTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\DeleteTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\UpdateTransactionalEmailTool;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;

function initializeMcpPayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'Maildun test client',
                'version' => '1.0.0',
            ],
        ],
    ];
}

function actingAsMcpUser(User $user, array $scopes): void
{
    app()->instance(ResourceServer::class, Mockery::mock(ResourceServer::class));
    Passport::actingAs($user, $scopes);
}

test('publishes OAuth discovery metadata required by remote MCP clients', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp/maildun')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('resource', url('/mcp/maildun'))
            ->where('authorization_servers.0', url('/'))
            ->where('scopes_supported.0', 'mcp:use'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('issuer', url('/'))
            ->where('authorization_endpoint', route('passport.authorizations.authorize'))
            ->where('token_endpoint', route('passport.token'))
            ->where('registration_endpoint', url('/oauth/register'))
            ->where('token_endpoint_auth_methods_supported.0', 'none')
            ->where('code_challenge_methods_supported.0', 'S256')
            ->where('scopes_supported.0', 'mcp:use')
            ->where('grant_types_supported.0', 'authorization_code')
            ->where('grant_types_supported.1', 'refresh_token')
            ->etc());
});

test('schedules expired and revoked OAuth credentials for cleanup', function () {
    $commands = collect(app(Schedule::class)->events())
        ->pluck('command')
        ->filter()
        ->implode("\n");

    expect($commands)->toContain('passport:purge --hours=168');
});

test('rejects unauthenticated and incorrectly scoped remote MCP requests', function () {
    app()->instance(ResourceServer::class, Mockery::mock(ResourceServer::class));

    $this->postJson('/mcp/maildun', initializeMcpPayload())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');

    $user = User::factory()->create();
    actingAsMcpUser($user, []);

    $this->postJson('/mcp/maildun', initializeMcpPayload())
        ->assertForbidden();
});

test('accepts remote MCP requests only with the MCP OAuth scope', function () {
    $user = User::factory()->create();
    actingAsMcpUser($user, ['mcp:use']);

    $this->postJson('/mcp/maildun', initializeMcpPayload())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Maildun MCP Server')
        ->assertJsonPath('result.instructions', fn (string $instructions): bool => str_contains(
            $instructions,
            'Remote connections can only use workspaces available to the authenticated user.',
        ));
});

test('dynamic client registration only accepts configured redirect origins', function () {
    config()->set('mcp.redirect_domains', ['https://chatgpt.com', 'https://grok.com']);

    $this->postJson('/oauth/register', [
        'client_name' => 'Untrusted client',
        'redirect_uris' => ['https://example.com/oauth/callback'],
    ])->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
    ])->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->whereType('client_id', 'string')
            ->where('grant_types.0', 'authorization_code')
            ->where('response_types.0', 'code')
            ->where('redirect_uris.0', 'https://chatgpt.com/connector/oauth/test-callback')
            ->where('scope', 'mcp:use')
            ->where('token_endpoint_auth_method', 'none')
            ->missing('client_secret'));
});

test('dynamic client registration accepts the allowed private-use scheme for native clients', function () {
    config()->set('mcp.redirect_domains', ['https://chatgpt.com', 'https://grok.com']);

    expect(config('mcp.custom_schemes'))->toContain('cursor');

    $this->postJson('/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
    ])->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->whereType('client_id', 'string')
            ->where('grant_types.0', 'authorization_code')
            ->where('response_types.0', 'code')
            ->where('redirect_uris.0', 'cursor://anysphere.cursor-mcp/oauth/callback')
            ->where('scope', 'mcp:use')
            ->where('token_endpoint_auth_method', 'none')
            ->missing('client_secret'));
});

test('dynamic client registration rejects private-use schemes that are not allowed', function () {
    config()->set('mcp.redirect_domains', ['https://chatgpt.com', 'https://grok.com']);
    config()->set('mcp.custom_schemes', ['cursor']);

    $rejectedRedirectUris = [
        'vscode://anysphere.cursor-mcp/oauth/callback',
        'claude://oauth/callback',
        'cursor:///oauth/callback',
    ];

    foreach ($rejectedRedirectUris as $redirectUri) {
        $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted native client',
            'redirect_uris' => [$redirectUri],
        ])->assertBadRequest()
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    $this->assertDatabaseMissing('oauth_clients', ['name' => 'Untrusted native client']);
});

test('authenticated MCP users only see workspaces they belong to', function () {
    $user = User::factory()->create();
    $memberWorkspace = Team::factory()->create([
        'name' => 'Member Workspace',
        'slug' => 'member-workspace',
    ]);
    $memberWorkspace->members()->attach($user, ['role' => TeamRole::Member->value]);
    Team::factory()->create([
        'name' => 'Hidden Workspace',
        'slug' => 'hidden-workspace',
    ]);

    MaildunServer::actingAs($user)
        ->tool(ListWorkspacesTool::class, ['search' => 'Workspace'])
        ->assertOk()
        ->assertDontSee('Hidden Workspace')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('workspaces.0.name', 'Member Workspace')
            ->etc());

    MaildunServer::actingAs($user)
        ->tool(CreateAudienceTool::class, [
            'workspace' => 'hidden-workspace',
            'name' => 'Cross-tenant audience',
        ])
        ->assertHasErrors(['workspace was not found']);
});

test('MCP writes enforce the workspace role policies', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create(['name' => 'Existing audience']);
    $campaign = Email::factory()->for($team)->create(['name' => 'Existing campaign']);
    $transactionalEmail = TransactionalEmail::factory()->for($team)->create(['name' => 'Existing transactional email']);

    $unauthorizedCalls = [
        [CreateAudienceTool::class, [
            'workspace' => $team->slug,
            'name' => 'Unauthorized audience',
        ]],
        [UpdateAudienceTool::class, [
            'workspace' => $team->slug,
            'uuid' => $audience->uuid,
            'name' => 'Changed audience',
        ]],
        [DeleteAudienceTool::class, [
            'workspace' => $team->slug,
            'uuid' => $audience->uuid,
            'confirm_name' => $audience->name,
        ]],
        [CreateCampaignTool::class, [
            'workspace' => $team->slug,
            'name' => 'Unauthorized campaign',
        ]],
        [UpdateCampaignTool::class, [
            'workspace' => $team->slug,
            'uuid' => $campaign->uuid,
            'name' => 'Changed campaign',
        ]],
        [DeleteCampaignTool::class, [
            'workspace' => $team->slug,
            'uuid' => $campaign->uuid,
            'confirm_name' => $campaign->name,
        ]],
        [CreateTransactionalEmailTool::class, [
            'workspace' => $team->slug,
            'name' => 'Unauthorized transactional email',
        ]],
        [UpdateTransactionalEmailTool::class, [
            'workspace' => $team->slug,
            'uuid' => $transactionalEmail->uuid,
            'name' => 'Changed transactional email',
        ]],
        [DeleteTransactionalEmailTool::class, [
            'workspace' => $team->slug,
            'uuid' => $transactionalEmail->uuid,
            'confirm_name' => $transactionalEmail->name,
        ]],
    ];

    foreach ($unauthorizedCalls as [$tool, $arguments]) {
        MaildunServer::actingAs($member)
            ->tool($tool, $arguments)
            ->assertHasErrors(['unauthorized']);
    }

    MaildunServer::actingAs($owner)
        ->tool(CreateAudienceTool::class, [
            'workspace' => $team->slug,
            'name' => 'Authorized audience',
        ])
        ->assertOk();

    $this->assertDatabaseMissing('audiences', ['name' => 'Unauthorized audience']);
    $this->assertDatabaseMissing('emails', ['name' => 'Unauthorized campaign']);
    $this->assertDatabaseMissing('transactional_emails', ['name' => 'Unauthorized transactional email']);
    $this->assertDatabaseHas('audiences', [
        'team_id' => $team->id,
        'name' => 'Authorized audience',
    ]);
    expect($audience->refresh()->name)->toBe('Existing audience')
        ->and($campaign->refresh()->name)->toBe('Existing campaign')
        ->and($transactionalEmail->refresh()->name)->toBe('Existing transactional email');
});
