<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="public/assets/img/logo-white.svg">
    <img src="public/assets/img/logo.svg" alt="Maildun" width="88">
  </picture>
</p>

<h1 align="center">Maildun</h1>

<p align="center">
  Self-hosted email marketing, transactional email, and automations for teams — on your own infrastructure.
</p>

<p align="center">
  <a href="https://github.com/Maildun/maildun/actions/workflows/tests.yml"><img src="https://github.com/Maildun/maildun/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-AGPL--3.0-blue" alt="License: AGPL-3.0"></a>
  <a href="#requirements"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
</p>

Maildun is a single application for the whole outbound email lifecycle: audiences and subscribe forms, campaigns built in a visual editor, transactional email behind a JSON API, event-driven automations, and first-party open and click tracking. It runs on your servers, sends through your Amazon SES or SMTP account, and keeps subscriber data out of third-party hands.

It is built for teams that have outgrown a hosted newsletter tool, or that cannot hand a customer list to one in the first place.

> [!IMPORTANT]
> Maildun is under active development and currently in beta. Review the [production deployment guide](docs/production.md) and [security policy](SECURITY.md) before using it with production data or sending email at scale.

## Features

- **Audiences** — custom attributes, tags, segments, list hygiene, double opt-in, CSV imports, and hosted subscribe forms with configurable branding
- **Campaigns** — visual email builder, reusable templates, scheduling, attachments, test sends, and per-campaign engagement insights
- **Transactional email** — reusable templates published behind a team-scoped, versioned JSON API
- **Automations** — event-based flows with timers, driven by durable jobs that resume after a restart
- **Delivery** — Amazon SES or SMTP, configured and verified per workspace, with bounce and complaint processing for SES
- **Tracking** — first-party open and click tracking with optional local geolocation databases; no third-party pixels
- **Teams** — workspaces, invitations, roles, two-factor authentication, and passkeys
- **Operations** — local or S3-compatible media storage, Redis-backed queues with Laravel Horizon, and a built-in [MCP server](docs/mcp.md) for AI-assisted workspace operations

## Technology

Laravel 13 · Inertia 3 · React 19 · TypeScript · Tailwind CSS 4 · Redis · Laravel Horizon · Pest

## Email delivery

Maildun deliberately separates two kinds of outbound email:

- **System email** uses the application-wide `MAIL_*` environment settings for account verification, password resets, workspace invitations, and operational notifications.
- **Workspace email** uses one delivery connection per workspace for campaigns, automations, and transactional email. The connection is tested first; every exact sender address is then verified against that connection.

Adding a sender or provider in workspace settings does not configure system email. A self-hosted installation must configure a working application-wide mailer before enabling registration or inviting other users, then configure each workspace provider before sending to its audience.

## Requirements

| Component | Version                                                   |
| --------- | --------------------------------------------------------- |
| PHP       | 8.4 or newer                                              |
| Composer  | 2                                                         |
| Node.js   | 22                                                        |
| pnpm      | 11                                                        |
| Redis     | any supported release                                     |
| Database  | any database supported by Laravel; SQLite is the default for local development |

## Quick start

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

`composer setup` installs dependencies, runs the interactive `app:install` command, and builds the frontend. `composer dev` starts the web server, Vite, Horizon, and the log viewer. Open <http://localhost:8000> and sign in with the administrator account the installer created.

For automation timers, segment synchronization, tracking recovery, and cleanup tasks, also run:

```bash
php artisan schedule:work
```

To explore with sample audiences and campaigns, pass `--demo` to the installer. It never adds demo data unless asked, and refuses to on production without `--force`.

### With Docker

`Dockerfile` and `docker-compose.yml` bring up the web server, Horizon, the scheduler, PostgreSQL, and Redis together:

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

| Guide                                                      | Covers                                                          |
| ---------------------------------------------------------- | --------------------------------------------------------------- |
| [Installation](docs/installation.md)                       | Requirements, automatic and manual setup, Docker, demo data     |
| [Configuration](docs/configuration.md)                     | Environment variables and their defaults                        |
| [Production deployment](docs/production.md)                | Process supervision, scheduling, backups, hardening             |
| [Amazon SES delivery](docs/email-delivery/amazon-ses.md)   | Connecting a workspace to SES, feedback notifications           |
| [SMTP delivery](docs/email-delivery/smtp.md)               | Connecting a workspace to an SMTP provider                      |
| [JSON API](API.md) · [OpenAPI spec](openapi.yaml)          | Authentication, transactional sends, subscription management    |
| [MCP](docs/mcp.md)                                         | Operating a workspace from an AI assistant                      |

## Development

Run the same checks used by continuous integration:

```bash
composer validate
composer ci:check
composer audit --locked
pnpm audit --audit-level=high
```

`composer ci:check` runs ESLint, Prettier, PHPStan, Pint, and the Pest suite. Maildun is maintained hands-on right now: contributions come in as issues, and pull requests are accepted by prior agreement only. See [CONTRIBUTING.md](CONTRIBUTING.md) for how that works, test expectations, and licensing of contributions. Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Support

- **Bugs and feature requests** — open a [GitHub issue](https://github.com/Maildun/maildun/issues/new/choose).
- **Security vulnerabilities** — use [private vulnerability reporting](https://github.com/Maildun/maildun/security/advisories/new), never a public issue. See [SECURITY.md](SECURITY.md).
- **Commercial licensing** — see [Removing the attribution](#removing-the-attribution) below.

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
