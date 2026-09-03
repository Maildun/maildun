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
use App\Mcp\Tools\Campaigns\UpdateCampaignTool;
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
#[Instructions('Provides access to Maildun workspaces, audiences, campaigns, transactional emails, and automations. Remote connections can only use workspaces available to the authenticated user. Start with application-status, then list-workspaces. Every domain tool requires a workspace slug; record identifiers are always scoped to that workspace.')]
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
        ListCampaignsTool::class,
        GetCampaignTool::class,
        CreateCampaignTool::class,
        UpdateCampaignTool::class,
        DeleteCampaignTool::class,
        ListTransactionalEmailsTool::class,
        GetTransactionalEmailTool::class,
        CreateTransactionalEmailTool::class,
        UpdateTransactionalEmailTool::class,
        DeleteTransactionalEmailTool::class,
        ListAutomationsTool::class,
        GetAutomationTool::class,
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
