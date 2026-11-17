# Security policy

## Reporting a vulnerability

Do not disclose suspected vulnerabilities in a public issue, discussion, pull request, or chat transcript.

Use GitHub's private vulnerability reporting for this repository:

https://github.com/Maildun/maildun/security/advisories/new

Include the affected commit or release, deployment details that matter, reproduction steps, impact, and any proposed mitigation. Remove real credentials, subscriber data, message content, and other personal data from the report.

The maintainer will acknowledge a complete report as soon as practical, investigate it, and coordinate remediation and disclosure. Response times are best-effort because this is a community-maintained project.

## Supported versions

Security fixes are applied to the current `main` branch and, when tagged releases exist, the latest release line. Older commits and forks are not maintained by this project.

## Known issues

`composer audit` reports no advisories. `pnpm-workspace.yaml` ignores one `pnpm audit` advisory that has no fixed release yet. Each accepted advisory is listed here with the reason it cannot be fixed yet.

- **GHSA-fc5q-39xc-vjpf** (high): ReDoS in `picomatch` 4.0.7 and earlier, which includes the latest release. Maildun locks 2.3.2 and 4.0.7. Only the Vite build plugins (`vite`, `tinyglobby`, `laravel-vite-plugin`) load it, to match file globs from the project's own configuration while assets are built. It is not part of the PHP application or the browser bundle, and no request input reaches it. The ignore is removed and an `overrides` floor added once a fixed version is published.

## Operator responsibilities

Self-hosting transfers important security responsibilities to the operator:

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a correct HTTPS `APP_URL`.
- Generate a unique `APP_KEY`, store it as a secret, and back it up. It protects encrypted workspace mail credentials and automation tokens.
- Keep registration invitation-only unless public sign-up is intentional.
- Restrict Horizon with `HORIZON_ALLOWED_EMAILS` and configure trusted proxies narrowly.
- Run the web application, Horizon, Redis, database, and scheduler with least-privilege service accounts.
- Keep dependencies and the host operating system patched.
- Protect database backups and uploaded files; both can contain personal or confidential data.
- Use provider-side rate limits, reputation monitoring, and abuse controls before sending at scale.
- Review tracking retention and consent requirements for every jurisdiction in which the installation operates.

See [Production deployment](docs/production.md) for the full checklist.
