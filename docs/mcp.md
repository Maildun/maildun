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
| Block email builder | `get-email-builder`, `edit-email-builder`, `check-email-builder` | Native block operations on campaign and transactional email designs, with revision checks and matching HTML rendering. |

List tools support `search` and `limit` (up to 100). Campaign and transactional-email tools accept the content fields relevant to the workspace editor, including HTML, source content, and builder designs where applicable. Sender addresses must already be authorized in the selected workspace.

Delete tools require both the record UUID and an exact `confirm_name`. They are destructive: an audience is permanently deleted, while campaigns and transactional emails are soft-deleted. Ask the client to fetch and repeat the target name before deleting anything.

## Native block editing

Use the builder tools when the email's `editor` is `builder`. They support `target: "campaign"` and `target: "transactional_email"`, using the same workspace slug and record UUID as the existing domain tools. Create a draft with `create-campaign` or `create-transactional-email` in a workspace configured for the block builder before composing a new email.

1. Call `get-email-builder` with `workspace`, `target`, and `uuid`. It returns a compact block outline, a `revision`, and the installed builder package's native tool schemas and instructions. Supply `block_id` to inspect one block and its children.
2. Call `edit-email-builder` with those identifiers, the latest `revision`, a native `tool` name, and its `input` arguments. The edit saves design JSON and rendered delivery HTML together; campaign plain text and transactional merge variables are also updated.
3. Call `check-email-builder` to read the rendered HTML, plain text, and validation warnings. Checking does not save or send anything.

Available native edits are `insert_blocks`, `update_block`, `move_block`, `remove_block`, `duplicate_block`, `replace_block`, `insert_section`, `update_settings`, `update_theme`, `replace_document`, and `apply_ops`. Use the returned schemas for their input shapes. `apply_ops` applies a batch atomically: if any operation fails, none of the changes are saved.

For example, after reading a campaign's outline:

```json
{
  "workspace": "acme-marketing",
  "target": "campaign",
  "uuid": "<campaign UUID>",
  "revision": "<revision from get-email-builder>",
  "tool": "update_block",
  "input": {
    "id": "hero-title",
    "props": { "text": "Our October update" }
  },
  "dry_run": true
}
```

Use a block ID and properties appropriate to its type. A dry run returns the proposed outline and operations with `saved: false`; it leaves the email and revision unchanged. To apply the change, submit the edit with `dry_run: false`. Every saved edit returns a new revision for the next call. If the email changes between reading and saving, the edit is rejected; read it again before retrying. The revision is checked again after rendering, inside the save transaction.

Campaign edits are limited to drafts. Transactional definitions follow the existing update policy, including edits to published definitions; the tool does not change publication status. Read-only members can inspect and check designs but cannot edit them, including dry runs. Legacy EmailBuilder.js designs are converted in memory when read and stored in the current format on a successful edit. A builder email with no design preserves its existing HTML as an HTML block.

These tools use the installed `@maildun/email-builder` library, without an AI provider or API key. Keep JavaScript dependencies installed on the application server and make Node.js 20+ available to the PHP process. `MCP_EMAIL_BUILDER_RUNTIME` defaults to `node`; set it to an absolute Node.js or Bun executable path when needed, then rebuild cached configuration. Each operation has a 20-second timeout. Email templates and non-builder editor modes are outside this tool set.

## Authorization and safety

Remote MCP access is tenant-scoped. The server only exposes workspaces in the authenticated user's memberships, and it checks Maildun's existing create, update, and delete policies for every write. If a tool returns `unauthorized`, use an account with the appropriate workspace role or ask a workspace administrator to perform the action.

Treat a remote MCP client as an actor with the same ability to change data as the signed-in user. Connect only clients you trust, review each requested mutation, and avoid giving a client sensitive material such as delivery credentials, trigger secrets, or database access. The server does not return automation trigger secrets.

## Troubleshooting

- **The local server does not appear:** make sure the client is using the Maildun checkout as its working directory and that PHP dependencies are installed with `composer install`.
- **Remote sign-in fails or the callback is rejected:** verify the public `APP_URL`, use HTTPS, and add the client callback origin to `MCP_REDIRECT_DOMAINS`. For a desktop callback, allow its exact custom scheme in `config/mcp.php`.
- **A workspace is missing:** confirm that the signed-in account is a member of that workspace, then run `list-workspaces` again.
- **A write is rejected:** check the workspace slug, record UUID, and role permissions. For deletes, make sure `confirm_name` exactly matches the record name.
