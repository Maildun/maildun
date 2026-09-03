# Model Context Protocol (MCP)

Maildun exposes an MCP server so an AI client can inspect and manage Maildun workspaces. The server returns structured data. Remote connections are restricted to the authenticated user's memberships and enforce the same workspace policies as the web application.

Use the local connection when your AI client is running on the same machine as a Maildun checkout. Use the remote connection when the client needs to reach a deployed Maildun instance.

## Local connection

The repository includes a local `maildun` server in [`.mcp.json`](../.mcp.json). Open the project in an MCP-capable development client and enable that server. The client starts this command from the project directory and communicates with it over standard input and output:

```json
{
  "mcpServers": {
    "maildun": {
      "command": "php",
      "args": ["artisan", "mcp:start", "maildun"]
    }
  }
}
```

If your client does not discover the project configuration automatically, add the same server definition to its MCP settings and set its working directory to the Maildun checkout. The local server is intended for a trusted developer environment; it does not use browser sign-in or OAuth. It runs without a Maildun user, so it can see all workspaces and bypasses per-user policy checks. Never expose it to an untrusted user or agent.

Do not start `php artisan mcp:start maildun` in a normal terminal as a health check. It is a long-running stdio process and will wait for MCP protocol messages from a client.

## Remote connection

For a deployed instance, add a remote MCP server using this URL:

```text
https://maildun.example.com/mcp/maildun
```

Replace the example origin with the instance's public `APP_URL`. The client should discover the OAuth metadata, open the Maildun sign-in and authorization flow, and request the `mcp:use` scope. Sign in with the Maildun account that should access the workspace; access is limited to that account's workspace memberships.

An operator must use HTTPS and set the correct public `APP_URL`. The client callback origin also has to be permitted by `MCP_REDIRECT_DOMAINS`; the default permits `https://chatgpt.com` and `https://grok.com`. Native desktop clients that use a custom callback scheme need that scheme added to `mcp.custom_schemes` in [`config/mcp.php`](../config/mcp.php). Do not broaden either allowlist with a wildcard.

The remote endpoint requires OAuth, rate limiting, and the `mcp:use` scope. A `401` response from a direct unauthenticated request is expected; connect through an MCP client instead of copying a bearer token into a prompt.

## Working with the server

Start each session with `application-status`, then call `list-workspaces`. It returns the workspace name and slug. Every other domain tool requires that slug in its `workspace` argument, and record tools use the UUID returned by a list or get operation.

For example, a useful request to an MCP-capable AI client is:

> Check the Maildun connection, list my workspaces, and show the campaigns in `acme-marketing`. Do not make changes.

For a change, make the requested action explicit and ask the client to confirm the target before it calls a mutation:

> In `acme-marketing`, create a draft campaign named “September update” with the subject “What’s new in September”. Show me the resulting UUID.

## Available tools

| Area | Tools | Notes |
| --- | --- | --- |
| Connection | `application-status` | Reports the server readiness and environment. |
| Workspaces | `list-workspaces` | Lists the authenticated user's workspaces remotely; the trusted local server lists all workspaces. |
| Audiences | `list-audiences`, `get-audience`, `create-audience`, `update-audience`, `delete-audience` | Lists include subscriber and segment counts. |
| Campaigns | `list-campaigns`, `get-campaign`, `create-campaign`, `update-campaign`, `delete-campaign` | Creation and updates manage draft campaign definitions. |
| Transactional email | `list-transactional-emails`, `get-transactional-email`, `create-transactional-email`, `update-transactional-email`, `delete-transactional-email` | Manages transactional email definitions and their merge variables. |
| Automations | `list-automations`, `get-automation` | Read-only; trigger secrets are never returned. |

List tools support `search` and `limit` (up to 100). Campaign and transactional-email tools accept the content fields relevant to the workspace editor, including HTML, source content, and builder designs where applicable. Sender addresses must already be authorized in the selected workspace.

Delete tools require both the record UUID and an exact `confirm_name`. They are destructive: an audience is permanently deleted, while campaigns and transactional emails are soft-deleted. Ask the client to fetch and repeat the target name before deleting anything.

## Authorization and safety

Remote MCP access is tenant-scoped. The server only exposes workspaces in the authenticated user's memberships, and it checks Maildun's existing create, update, and delete policies for every write. If a tool returns `unauthorized`, use an account with the appropriate workspace role or ask a workspace administrator to perform the action.

Treat a remote MCP client as an actor with the same ability to change data as the signed-in user. Connect only clients you trust, review each requested mutation, and avoid giving a client sensitive material such as delivery credentials, trigger secrets, or database access. The server does not return automation trigger secrets.

## Troubleshooting

- **The local server does not appear:** make sure the client is using the Maildun checkout as its working directory and that PHP dependencies are installed with `composer install`.
- **Remote sign-in fails or the callback is rejected:** verify the public `APP_URL`, use HTTPS, and add the client callback origin to `MCP_REDIRECT_DOMAINS`. For a desktop callback, allow its exact custom scheme in `config/mcp.php`.
- **A workspace is missing:** confirm that the signed-in account is a member of that workspace, then run `list-workspaces` again.
- **A write is rejected:** check the workspace slug, record UUID, and role permissions. For deletes, make sure `confirm_name` exactly matches the record name.
