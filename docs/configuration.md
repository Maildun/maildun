# Configuration

Copy `.env.example` to `.env` and treat the resulting file as a secret. This page explains the settings that are specific or especially important to Maildun; Laravel's standard settings retain their normal behavior.

## Application and access

| Variable | Purpose | Production guidance |
| --- | --- | --- |
| `APP_KEY` | Encrypts application secrets and signed data | Generate once, back it up securely, and do not rotate casually |
| `APP_PREVIOUS_KEYS` | Former `APP_KEY` values kept as decryption fallbacks | Set during a rotation so encrypted workspace credentials keep working |
| `APP_URL` | Canonical origin for signed links and the passkey relying party | Use the public HTTPS origin |
| `APP_DEBUG` | Detailed exception output | Always `false` in production |
| `PASSKEYS_USER_HANDLE_SECRET` | Derives the WebAuthn user handle | Set it explicitly; left empty it defaults to `APP_KEY` |
| `REGISTRATION_ENABLED` | Allows public account creation | Prefer `false` unless open registration is intentional |
| `TRUSTED_PROXIES` | Trusts proxy-provided client addresses | Use explicit proxy addresses when possible; never set `*` on a directly exposed server |

Invited teammates can finish registration even when public registration is disabled.

`APP_URL` carries more weight than its name suggests. Queue workers serve no request and
cannot infer the host, so open pixels, click redirects, and unsubscribe links are signed
against this value; changing it invalidates the links in every message already delivered.
Its host is also the passkey relying party ID, so a change locks people out of passkey
sign-in unless `PASSKEYS_USER_HANDLE_SECRET` was set independently.

## Database, Redis, and queues

SQLite is convenient locally and is the default. PostgreSQL and MySQL/MariaDB are also supported — set `DB_CONNECTION` and uncomment the connection details in `.env.example`. Use a managed or operationally backed-up database for production workloads.

Maildun defaults to Redis for cache and queues. Horizon consumes four queue groups:

- `default` for general application work;
- `tracking` for engagement processing;
- `transactional` for transactional messages; and
- `campaigns` for bulk sends.

The `MAIL_*_QUEUE` variables can point all work to `default` for a small installation. Horizon process caps and wait thresholds are controlled by the `HORIZON_*` variables in `.env.example`.

## Email delivery

Each workspace configures one provider under **Settings → Email delivery**. Those credentials are encrypted in the database with `APP_KEY`. Delivery is tested independently with explicit From and To addresses; workspace sender addresses are verified separately against the current connection version.

The application-wide `MAIL_*` values are used for system notifications, never as a fallback for workspace campaigns or transactional email. `MAIL_SES_*` belongs to the optional platform-wide SES mailer. It is deliberately separate from the `AWS_*` and `R2_*` object-storage settings.

See [Amazon SES](email-delivery/amazon-ses.md) and [SMTP](email-delivery/smtp.md).

Set `MAIL_RATE_LIMIT_PER_SECOND` to a value within the provider's accepted send rate. A value of `0` disables application-side limiting. `MAIL_RATE_LIMIT_RELEASE_AFTER` controls how many seconds a send job waits before asking for a slot again.

## Storage

`FILESYSTEM_DISK=local` stores uploads on the server. Run `php artisan storage:link` and include uploaded files in backups.

For cloud storage, set `FILESYSTEM_DISK=s3`, choose `OBJECT_STORAGE_PROVIDER=aws` or `OBJECT_STORAGE_PROVIDER=r2`, and configure private and public buckets separately.

AWS S3 uses:

- `AWS_BUCKET` stores attachments and other private files;
- `AWS_PUBLIC_BUCKET` stores public media, logos, and avatars;
- `AWS_PUBLIC_URL` optionally points to a public CDN or custom origin; and
- `AWS_ENDPOINT` remains empty for AWS S3.

Cloudflare R2 uses the equivalent dedicated `R2_BUCKET`, `R2_PUBLIC_BUCKET`, and `R2_PUBLIC_URL` values. Set `R2_ENDPOINT` to the account's S3 API endpoint. Access keys are likewise separate as `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` and `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY`.

For another S3-compatible provider, select the custom provider in `php artisan storage:configure`; it uses the `AWS_*` section with `AWS_ENDPOINT` and `AWS_PUBLIC_URL`.

Use `php artisan storage:configure` to validate a backend and `php artisan storage:sync --dry-run` before migrating files.

## Tracking and privacy

Maildun stores first-party open and click events, including technical request metadata. `MAIL_TRACKING_EVENT_RETENTION_DAYS` defaults to 90 days; aggregated campaign insights remain after detailed events are pruned. Set it to the shortest period your use case requires, or `0` only when indefinite detailed retention is intentional.

Geolocation uses local DB-IP Lite `.mmdb` files configured with `MAIL_TRACKING_CITY_DATABASE` and `MAIL_TRACKING_ASN_DATABASE`. These databases are not included in the repository.

## Horizon access and alerts

Outside local development, `/horizon` is limited to the comma-separated accounts in `HORIZON_ALLOWED_EMAILS`. Leave no production dashboard accidentally public.

Set `HORIZON_NOTIFICATION_EMAIL` to receive long-wait alerts and keep the scheduled `horizon:snapshot` command running for metrics.

## MCP

Maildun provides a local MCP server through `.mcp.json` and a remote OAuth-protected endpoint at `/mcp/maildun`. The remote endpoint requires the `mcp:use` scope and is restricted to the authenticated user's workspace memberships. Keep `APP_URL` accurate, allow only trusted client callback origins in `MCP_REDIRECT_DOMAINS`, and never use a wildcard for a network-accessible deployment.

See [Using MCP](mcp.md) for local and remote connection instructions, supported tools, and troubleshooting.

## Secrets

Never commit `.env`, provider credentials, API keys, database dumps, OAuth keys, `.mmdb` databases, or generated storage content. Prefer the hosting platform's secret manager and restrict access to backups and logs.
