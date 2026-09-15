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

### insane (GHSA-w455-mfq9-hf74), regular expression denial of service

`insane` enters the dependency tree through `@usewaypoint/block-text`, which the
email builder uses to sanitize text blocks. No fixed release exists. The advisory
names 2.6.3, but npm has never published anything past 2.6.2 (June 2022) and the
package is unmaintained, so there is no version to upgrade to.

The sanitizer runs in the browser, inside `renderBuilderHtml` in
`resources/js/lib/email-builder.ts`, while an authenticated member composes a
campaign, over HTML that same member authored. It does not run on the server:
`App\Actions\Emails\RenderCampaignContent` performs merge-tag substitution in PHP,
and recipients receive static HTML. A pathological input therefore stalls the
composing browser tab and nothing else.

`pnpm audit --prod` reports this advisory until the email builder drops `insane`
upstream or the text block is replaced.

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
