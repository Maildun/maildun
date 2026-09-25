<?php

use App\Enums\EmailEditor;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\TransactionalEmails\CreateTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\DeleteTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\GetTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\ListTransactionalEmailsTool;
use App\Mcp\Tools\TransactionalEmails\UpdateTransactionalEmailTool;
use App\Models\Audience;
use App\Models\Team;
use App\Models\TransactionalEmail;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages a transactional email through its MCP lifecycle', function () {
    $team = Team::factory()->create(['slug' => 'product']);

    MaildunServer::tool(CreateTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'name' => 'Password Changed',
        'slug' => 'password-changed',
        'subject' => 'Password changed for {{ first_name }}',
        'html' => '<p>Hello {{ first_name }}</p>',
        'variables' => [['key' => 'first_name', 'example' => 'Taylor']],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('transactional_email.name', 'Password Changed')
        ->where('transactional_email.slug', 'password-changed')
        ->where('transactional_email.variables.0.key', 'first_name')
        ->etc());

    $email = TransactionalEmail::query()
        ->whereBelongsTo($team)
        ->where('slug', 'password-changed')
        ->firstOrFail();

    MaildunServer::tool(GetTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'uuid' => $email->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('transactional_email.uuid', $email->uuid)
        ->where('transactional_email.slug_frozen', false)
        ->etc());

    MaildunServer::tool(UpdateTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'uuid' => $email->uuid,
        'description' => 'Sent after a password change.',
        'html' => '<p>Hello {{ first_name }}, contact {{ support_email }}</p>',
    ])->assertOk();

    expect($email->refresh())
        ->description->toBe('Sent after a password change.')
        ->variables->toHaveCount(2)
        ->and($email->variables[1]['key'])->toBe('support_email');

    MaildunServer::tool(ListTransactionalEmailsTool::class, [
        'workspace' => $team->slug,
        'status' => 'draft',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('transactional_emails.0.uuid', $email->uuid)
        ->etc());

    $email->forceFill(['published_at' => now()])->save();

    MaildunServer::tool(UpdateTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'uuid' => $email->uuid,
        'slug' => 'new-identifier',
    ])->assertHasErrors(['cannot change after the first publish']);

    MaildunServer::tool(DeleteTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'uuid' => $email->uuid,
        'confirm_name' => $email->name,
    ])->assertOk();

    expect($email->refresh()->trashed())->toBeTrue();
});

test('refuses to delete a transactional email an audience confirms signups with', function () {
    $team = Team::factory()->create(['slug' => 'confirmations']);
    $email = TransactionalEmail::factory()->for($team)->published()->create();
    Audience::factory()->for($team)->create([
        'name' => 'Newsletter',
        'double_opt_in' => true,
        'double_opt_in_email_id' => $email->id,
    ]);

    MaildunServer::tool(DeleteTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'uuid' => $email->uuid,
        'confirm_name' => $email->name,
    ])->assertHasErrors(['Newsletter uses this as its double opt-in confirmation email.']);

    $this->assertNotSoftDeleted($email);
});

test('keeps markdown source when transactional email is managed through MCP', function () {
    $team = Team::factory()->create([
        'slug' => 'markdown-transactional',
        'email_editor' => EmailEditor::Markdown,
    ]);

    MaildunServer::tool(CreateTransactionalEmailTool::class, [
        'workspace' => $team->slug,
        'name' => 'Markdown receipt',
        'source' => '# Receipt **ready**',
        'html' => '<h1>Receipt <strong>ready</strong></h1>',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('transactional_email.editor', EmailEditor::Markdown->value)
        ->where('transactional_email.source', '# Receipt **ready**')
        ->etc());
});
