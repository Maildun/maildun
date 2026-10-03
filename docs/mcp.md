# Model Context Protocol (MCP)

Maildun exposes an MCP server so an AI client can inspect and manage Maildun workspaces. The server returns structured data. Remote connections are restricted to the authenticated user's memberships and enforce the same workspace policies as the web application.

Use the local connection when your AI client is running on the same machine as a Maildun checkout. Use the remote connection when the client needs to reach a deployed Maildun instance.

## Local connection

The repository includes a local `maildun` server in [`.mcp.json`](../.mcp.json). Open the project in an MCP-capable development client and enable that server. The separate `laravel-boost` entry provides development tools; enable `maildun` to work with application data. The client starts this command from the project directory and communicates with it over standard input and output:

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

Replace the example origin with the instance's public `APP_URL`. Use a client that supports remote HTTP MCP and OAuth authorization code with PKCE (`S256`). It should discover the OAuth metadata, register its callback, open the Maildun sign-in and authorization flow, and request the `mcp:use` scope. Dynamic registration creates a public client without a client secret. Sign in with the Maildun account that should access the workspace and approve access.

### Operator setup

Install Maildun and run its database migrations before connecting a remote client. Use HTTPS and set `APP_URL` to the public origin. The installer generates `APP_KEY`, but does not generate Passport's OAuth signing keys. On a first deployment without an existing key pair or environment-provided keys, run:

```bash
php artisan passport:keys --no-interaction
```

Passport normally reads `storage/oauth-private.key` and `storage/oauth-public.key`. Keep the same pair in persistent storage across releases and application servers, readable by the PHP process. Alternatively, supply the matching PEM values through `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` in the deployment's secret manager. Keep keys out of source control. Key generation follows [Passport's deployment guidance](https://laravel.com/docs/13.x/passport#deploying-passport).

The client callback origin must be permitted by `MCP_REDIRECT_DOMAINS`. Native clients with a custom callback scheme use `mcp.custom_schemes` in [`config/mcp.php`](../config/mcp.php) instead.

| Setting | Default | Purpose |
| --- | --- | --- |
| `MCP_REDIRECT_DOMAINS` | `https://chatgpt.com,https://grok.com` | Comma-separated allowed HTTP(S) callback origins, including scheme and host. |
| `mcp.custom_schemes` | `['cursor']` | Allowed native callback schemes. `claude` and `vscode` are commented out and must be explicitly enabled when needed. |
| `MCP_ACCESS_TOKEN_EXPIRATION_MINUTES` | `60` | Access-token lifetime; the application enforces a minimum of 5 minutes. |
| `MCP_REFRESH_TOKEN_EXPIRATION_DAYS` | `30` | Refresh-token lifetime; the application enforces a minimum of 1 day. |

Add only the callback origin or scheme actually used by a trusted client. Do not use a wildcard. After changing environment or configuration values on a deployment that caches configuration, rebuild it:

```bash
php artisan config:cache --no-interaction
```

The client handles access-token refresh. Reauthorize through the client if its refresh token expires or is revoked.

### OAuth discovery

The client discovers the following paths on the same public origin:

| Path | Purpose |
| --- | --- |
| `/.well-known/oauth-protected-resource/mcp/maildun` | MCP resource metadata and the authorization server location. |
| `/.well-known/oauth-authorization-server` | Authorization, token, registration, scope, and PKCE metadata. |
| `/oauth/register` | Dynamic client registration (`POST`). |
| `/oauth/authorize` | Browser sign-in and authorization. |
| `/oauth/token` | Authorization-code exchange and refresh-token requests (`POST`). |

Ensure the reverse proxy forwards these paths and the `Authorization` header to Laravel. The public origin advertised by discovery must match the HTTPS origin the client can reach.

The remote endpoint enforces OAuth, the `mcp:use` scope, and rate limits. An unauthenticated MCP `POST` receives `401` when Passport keys are configured correctly; a token without the required scope receives `403`. Connect through an MCP client instead of copying a bearer token into a prompt.

## Working with the server

Start each session with `application-status`, then call `list-workspaces`. Workspace results include `uuid`, `name`, `slug`, and `email_editor`. Every other domain tool requires the slug in its `workspace` argument; get, update, and delete tools require the record's `uuid`. Use identifiers returned by the server rather than internal database IDs. `application-status` reports that the MCP tool is reachable; it does not test email delivery, queues, or storage.

For example, a useful request to an MCP-capable AI client is:

> Check the Maildun connection, list my workspaces, and show the campaigns in `acme-marketing`. Do not make changes.

For a change, make the requested action explicit and ask the client to confirm the target before it calls a mutation:

> In `acme-marketing`, create a draft campaign named “September update” with the subject “What’s new in September”. Show me the resulting UUID.

The corresponding `create-campaign` arguments are:

```json
{
  "workspace": "acme-marketing",
  "name": "September update",
  "subject": "What's new in September"
}
```

To read it again, call `get-campaign` with `workspace` and the returned campaign `uuid`.

## Available tools

| Area | Tools | Notes |
| --- | --- | --- |
| Connection | `application-status` | Reports the server readiness and environment. |
| Workspaces | `list-workspaces` | Lists the authenticated user's workspaces remotely; the trusted local server lists all workspaces. |
| Audiences | `list-audiences`, `get-audience`, `create-audience`, `update-audience`, `delete-audience` | Lists include subscriber and segment counts. |
| Campaigns | `list-campaigns`, `get-campaign`, `create-campaign`, `update-campaign`, `delete-campaign` | Creates drafts; updates are limited to drafts. |
| Transactional email | `list-transactional-emails`, `get-transactional-email`, `create-transactional-email`, `update-transactional-email`, `delete-transactional-email` | Creates drafts and edits definitions and merge variables; a slug cannot change after first publication. |
| Automations | `list-automations`, `get-automation` | Read-only; trigger secrets are never returned. |
| Block email builder | `get-email-builder`, `edit-email-builder`, `check-email-builder` | Native block operations on campaign and transactional email designs, with revision checks and matching HTML rendering. |

List tools support `search` and `limit` (default 20, from 1 to 100). Campaign, transactional-email, and automation lists also accept `status`; use the values advertised by each tool's schema. Lists return `count` for the returned records, not a total across all matches, and do not expose pagination arguments.

Campaign and transactional-email tools accept content fields for the workspace editor: `html`, editable `source` for Markdown or plain text, and `design` for the block builder. A supplied design must include its `root` array. Creating a campaign requires only `workspace` and `name`; `subject` defaults to the name, and the body can be written later. Updates require at least one supported field to change. Sender addresses must already be authorized in the selected workspace. Campaign `audience_uuid` must belong to that workspace, and `segment_uuid` must belong to the selected audience.

These tools do not send or schedule campaigns, publish or send transactional emails, manage individual contacts or subscribers, or create, edit, or trigger automations. Use the web application or the [HTTP API](../API.md) for operations those interfaces support. The Maildun server currently exposes no MCP resources or prompts.

Delete tools require `workspace`, `uuid`, and an exact `confirm_name`. They are destructive: an audience is permanently deleted, while campaigns and transactional emails are soft-deleted. A campaign cannot be deleted while it is sending, and a transactional email cannot be deleted while it is used for an audience's double opt-in confirmation. Ask the client to fetch and repeat the target name before deleting anything.

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
- **`Invalid key supplied` or a server error during OAuth/MCP authentication:** check that the active release resolves to persistent `storage`, both Passport keys exist and are readable by PHP, and any `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` overrides contain a valid matching pair. Restore existing keys when possible. Run `passport:keys` only when provisioning a missing pair; `--force` overwrites existing keys and causes previously issued access tokens to fail verification.
- **A workspace is missing:** confirm that the signed-in account is a member of that workspace. Check `search` and `limit`, then run `list-workspaces` again.
- **The connection returns `401` or `403`:** reauthorize if the access token cannot be refreshed; make sure the client requests `mcp:use`. A browser session or a workspace HTTP API key does not replace the required OAuth token.
- **The connection returns `429`:** back off and retry. MCP POST requests are limited to 120 per minute per bearer-token fingerprint and 240 per minute per IP; OAuth discovery and registration are limited to 60 per minute per IP.
- **A write is rejected:** check the workspace slug, record UUID, and role permissions. For deletes, make sure `confirm_name` exactly matches the record name.
