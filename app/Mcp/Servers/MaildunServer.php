<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ApplicationStatusTool;
use App\Mcp\Tools\Audiences\CreateAudienceTool;
use App\Mcp\Tools\Audiences\DeleteAudienceTool;
use App\Mcp\Tools\Audiences\GetAudienceTool;
use App\Mcp\Tools\Audiences\ListAudiencesTool;
use App\Mcp\Tools\Audiences\UpdateAudienceTool;
use App\Mcp\Tools\Automations\GetAutomationTool;
use App\Mcp\Tools\Automations\ListAutomationsTool;
use App\Mcp\Tools\Campaigns\CreateCampaignTool;
use App\Mcp\Tools\Campaigns\DeleteCampaignTool;
use App\Mcp\Tools\Campaigns\GetCampaignTool;
use App\Mcp\Tools\Campaigns\ListCampaignsTool;
use App\Mcp\Tools\Campaigns\ScheduleCampaignTool;
use App\Mcp\Tools\Campaigns\SendCampaignTool;
use App\Mcp\Tools\Campaigns\SendTestCampaignTool;
use App\Mcp\Tools\Campaigns\StopCampaignTool;
use App\Mcp\Tools\Campaigns\UnscheduleCampaignTool;
use App\Mcp\Tools\Campaigns\UpdateCampaignTool;
use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\DeleteContactTool;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\EmailBuilder\CheckEmailBuilderTool;
use App\Mcp\Tools\EmailBuilder\EditEmailBuilderTool;
use App\Mcp\Tools\EmailBuilder\GetEmailBuilderTool;
use App\Mcp\Tools\SavedSections\ListSavedSectionsTool;
use App\Mcp\Tools\Segments\CreateSegmentTool;
use App\Mcp\Tools\Segments\DeleteSegmentTool;
use App\Mcp\Tools\Segments\GetSegmentTool;
use App\Mcp\Tools\Segments\ListSegmentsTool;
use App\Mcp\Tools\Segments\UpdateSegmentTool;
use App\Mcp\Tools\Subscribers\AddSubscriberTool;
use App\Mcp\Tools\Subscribers\ListSubscribersTool;
use App\Mcp\Tools\Subscribers\RemoveSubscriberTool;
use App\Mcp\Tools\Subscribers\ResubscribeSubscriberTool;
use App\Mcp\Tools\Subscribers\UnsubscribeSubscriberTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Mcp\Tools\TransactionalEmails\CreateTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\DeleteTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\GetTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\ListTransactionalEmailsTool;
use App\Mcp\Tools\TransactionalEmails\UpdateTransactionalEmailTool;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Maildun MCP Server')]
#[Version('1.0.0')]
#[Instructions('Provides access to Maildun workspaces, audiences, contacts, subscribers, segments, tags, campaigns, transactional emails, and automations. Remote connections can only use workspaces available to the authenticated user. Start with application-status, then list-workspaces. Every domain tool requires a workspace slug; record identifiers are always scoped to that workspace. For block email design, start with get-email-builder to discover native agent tools and the revision. Apply edits through edit-email-builder, use dry_run to preview and apply_ops for atomic batches, then check-email-builder to validate and preview. Re-read after a revision conflict. Subscribing people requires consent_confirmed; never resubscribe someone who unsubscribed without renewed consent. To deliver a campaign, use send-test-campaign first, then send-campaign or schedule-campaign with the exact campaign name; stop-campaign halts a send in progress. list-saved-sections returns the workspace saved sections; insert one with edit-email-builder using its blocks.')]
class MaildunServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ApplicationStatusTool::class,
        ListWorkspacesTool::class,
        ListAudiencesTool::class,
        GetAudienceTool::class,
        CreateAudienceTool::class,
        UpdateAudienceTool::class,
        DeleteAudienceTool::class,
        ListContactsTool::class,
        GetContactTool::class,
        CreateContactTool::class,
        UpdateContactTool::class,
        DeleteContactTool::class,
        ListSubscribersTool::class,
        AddSubscriberTool::class,
        UnsubscribeSubscriberTool::class,
        ResubscribeSubscriberTool::class,
        RemoveSubscriberTool::class,
        ListSegmentsTool::class,
        GetSegmentTool::class,
        CreateSegmentTool::class,
        UpdateSegmentTool::class,
        DeleteSegmentTool::class,
        ListTagsTool::class,
        ListCampaignsTool::class,
        GetCampaignTool::class,
        CreateCampaignTool::class,
        UpdateCampaignTool::class,
        DeleteCampaignTool::class,
        SendTestCampaignTool::class,
        SendCampaignTool::class,
        ScheduleCampaignTool::class,
        UnscheduleCampaignTool::class,
        StopCampaignTool::class,
        ListTransactionalEmailsTool::class,
        GetTransactionalEmailTool::class,
        CreateTransactionalEmailTool::class,
        UpdateTransactionalEmailTool::class,
        DeleteTransactionalEmailTool::class,
        ListAutomationsTool::class,
        GetAutomationTool::class,
        GetEmailBuilderTool::class,
        EditEmailBuilderTool::class,
        CheckEmailBuilderTool::class,
        ListSavedSectionsTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
