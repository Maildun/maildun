# Production deployment

This checklist describes the application processes and security controls a Maildun production installation needs. Adapt commands to the hosting platform and test restores before accepting real data.

## Before deployment

- Use a supported PHP version and install dependencies from the committed lockfiles.
- Provision a production database and Redis with authentication and private network access.
- Configure HTTPS and set `APP_URL` to the public origin.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and `REGISTRATION_ENABLED=false` unless open sign-up is intentional.
- Generate a unique `APP_KEY` and store it in the platform's secret manager.
- Configure trusted proxies, storage, backups, and an application-wide mailer.
- Decide how long detailed tracking events may be retained.

## Build and install

```bash
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm run build
php artisan app:install --registration=closed
php artisan optimize
```

Run the first installation interactively so the administrator password does not enter shell history or a deployment log. On later automated deployments, the idempotent installer can be used without administrator credentials:

```bash
php artisan app:install --no-interaction --force --registration=closed --skip-storage
```

The web server's document root must be the repository's `public` directory. The application process must not run as `root`, and only Laravel's required storage and cache directories should be writable.

## Required long-running processes

### Queue workers

Keep Horizon alive under a process manager:

```bash
php artisan horizon
```

Restart workers after every deployment so they load new code:

```bash
php artisan horizon:terminate
```

### Scheduler

Run this command every minute:

```bash
php artisan schedule:run
```

The schedule synchronizes segments, captures Horizon metrics, recovers stranded campaign and automation jobs, redispatches durable tracking events, prunes expired tracking payloads, and deletes expired invitations.

## Storage and backups

Back up and regularly restore-test:

- the database;
- `APP_KEY` and every deployment secret;
- local uploaded files, or both S3-compatible buckets; and
- infrastructure configuration needed to recreate workers, schedules, domains, and webhooks.

Losing `APP_KEY` makes encrypted workspace provider settings and tokens unreadable. A database-only backup is incomplete when local storage is in use.

## Email and webhooks

Every sending workspace must configure and test one connection under **Settings → Email delivery**, then verify every exact From address under **Settings → Sender**. Follow the [Amazon SES](email-delivery/amazon-ses.md) or [SMTP](email-delivery/smtp.md) guide.

For SES feedback, the application must be reachable by HTTPS and the SNS topic, region, and configuration set must match the workspace connection. Maildun verifies signed SNS messages; do not bypass that validation at a reverse proxy.

## Security checklist

- Keep public registration closed unless it is monitored and intentional.
- Allowlist Horizon accounts with `HORIZON_ALLOWED_EMAILS`.
- Use narrowly scoped trusted proxy addresses.
- Restrict database, Redis, and object storage to the application network.
- Apply operating-system, PHP, Composer, and npm security updates.
- Run `composer audit --locked` and `pnpm audit --audit-level=high` during deployment or CI.
- Protect logs and backups from exposing addresses, message content, credentials, or tracking metadata.
- Monitor bounces, complaints, queue delays, send rate, disk usage, and failed jobs.
- Test unsubscribe, double opt-in, and suppression behavior before a real campaign.

## Health checks after deployment

```bash
php artisan about
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
```

Then sign in, confirm that `/horizon` follows the configured access policy, upload a test asset, send a test email, exercise an unsubscribe link, and verify the scheduler and queues are moving.

No checklist can guarantee a secure deployment. Reassess the threat model whenever Maildun is exposed to a new network, tenant model, provider, or volume of personal data.
