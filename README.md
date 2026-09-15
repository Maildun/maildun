<p align="center">
  <img src="public/assets/img/logo.svg" alt="Maildun" width="88">
</p>

# Maildun

[![Tests](https://github.com/Maildun/maildun/actions/workflows/tests.yml/badge.svg)](https://github.com/Maildun/maildun/actions/workflows/tests.yml)

Maildun is a self-hosted email platform for teams that need audience management, campaigns, transactional email, automations, and first-party engagement tracking in one application.

> [!IMPORTANT]
> Maildun is under active development. Review the deployment and security guidance before using it with production data or sending email at scale.

## Features

- Team workspaces, invitations, roles, two-factor authentication, and passkeys
- Audiences, custom attributes, tags, segments, list hygiene, and double opt-in
- Hosted subscribe forms with configurable branding
- Visual email templates, campaign scheduling, attachments, and test sends
- Transactional templates and a team-scoped JSON API
- Event-based automations with durable recovery jobs
- Amazon SES and SMTP delivery configured per workspace
- First-party open and click tracking with optional local geolocation databases
- Local or S3-compatible media storage
- Redis-backed queues and Laravel Horizon monitoring
- A local Laravel MCP server for supported workspace operations

## Technology

Maildun is built with Laravel 13, Inertia 3, React 19, TypeScript, Tailwind CSS 4, Redis, Laravel Horizon, and Pest.

## Email delivery

Maildun deliberately separates two kinds of outbound email:

- **System email** uses the application-wide `MAIL_*` environment settings for account verification, password resets, workspace invitations, and operational notifications.
- **Workspace email** uses one delivery connection per workspace for campaigns, automations, and transactional email. The connection is tested first; every exact sender address is then verified against that connection.

Adding a sender or provider in workspace settings does not configure system email. A self-hosted installation must configure a working application-wide mailer before enabling registration or inviting other users, then configure each workspace provider before sending to its audience.

## Quick start

You need PHP 8.4 or newer, Composer 2, Node.js 22, pnpm 11, Redis, and a database supported by Laravel. SQLite is the default for local development.

```bash
git clone https://github.com/Maildun/maildun.git
cd maildun
cp .env.example .env
# Review the database, Redis, storage, and system MAIL_* values in .env.
touch database/database.sqlite
composer setup
composer dev
```

Start Redis before running the installer. The example environment expects a local SMTP relay at `127.0.0.1:2525`; start one for local development or set `MAIL_MAILER=log`. For a public installation, replace the example `MAIL_*` values with a real system mail relay before running the installer.

`composer setup` installs dependencies, runs the interactive `app:install` command, and builds the frontend. The development command starts the web server, Vite, Horizon, and the log viewer.

For automation timers, segment synchronization, tracking recovery, and cleanup tasks, also run:

```bash
php artisan schedule:work
```

### With Docker

`Dockerfile` and `docker-compose.yml` bring up the web server, Horizon, the
scheduler, PostgreSQL, and Redis together:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate --show   # paste into .env, and set DB_PASSWORD
docker compose up -d
docker compose exec app php artisan app:install --no-interaction \
    --admin-name="Your Name" --admin-email=you@example.com \
    --admin-password='a-strong-password' --registration=closed
```

See the [installation guide](docs/installation.md) for system mail configuration, a manual setup, demo data, and common problems.

## Production

A production deployment needs more than a web process. Keep Horizon supervised, run Laravel's scheduler every minute, configure both the application-wide system mailer and each sending workspace, and back up the database, `APP_KEY`, and uploaded files.

Read the [production deployment guide](docs/production.md) and [configuration reference](docs/configuration.md) before exposing an installation to the internet. New public installations should normally use invitation-only registration:

```bash
php artisan app:install --registration=closed
```

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [MCP](docs/mcp.md)
- [Production deployment](docs/production.md)
- [JSON API](API.md)
- [Amazon SES delivery](docs/email-delivery/amazon-ses.md)
- [SMTP delivery](docs/email-delivery/smtp.md)
- [Security policy](SECURITY.md)
- [Contributing](CONTRIBUTING.md)

## Development checks

Run the same checks used by continuous integration:

```bash
composer validate
composer ci:check
composer audit --locked
pnpm audit --audit-level=high
```

## Responsible use

Operators are responsible for consent, unsubscribe handling, sender identity, retention, and compliance with the laws and provider policies that apply to their recipients. Maildun is not a substitute for legal advice, a sending reputation program, or abuse monitoring.

## License

Copyright (C) 2026 Dunn.

Maildun is free software licensed under the [GNU Affero General Public License, version 3](LICENSE) (AGPL-3.0-only), **with additional terms under section 7** stated at the top of [LICENSE](LICENSE).

You may run, study, modify, and redistribute Maildun, including commercially. Hosting it for other people is allowed. The conditions are:

- **Keep the attribution.** Every interface must show a "Powered by Maildun" link to the source of the version you are running — in the app itself and on every public page (subscription forms, confirmation pages, unsubscribe pages). You may restyle it; you may not remove or hide it.
- **Publish your changes.** If you modify Maildun and let anyone use it over a network, section 13 requires you to offer those users the complete corresponding source of the version you are running. Point `APP_SOURCE_URL` at it.
- **Do not pass it off as your own.** Modified versions must be marked as different from the original and offered under a different name. No rights are granted to the Maildun name or logo.
- **Derivative works stay under the AGPL.**

Running an unmodified Maildun — for your own organization or as a hosted service for others — obliges you only to leave the attribution in place.

### Removing the attribution

The attribution and naming conditions are the only things standing between Maildun and a white-labeled resale of it. If you want to ship Maildun under your own brand, with the notice removed, that needs a separate commercial license: **werk@abduns.com**.

Third-party packages, artwork, and trademarks remain subject to their own terms; see [Third-party notices](THIRD_PARTY_NOTICES.md).
