# Contributing to Maildun

Thank you for your interest in improving Maildun.

Maildun is currently maintained hands-on by a single maintainer. Every report is read and triaged by hand, and changes are landed by the maintainer directly. For now, the way to contribute is to **open an issue**; unsolicited pull requests are not being accepted.

## How contributions work right now

- **Bug reports, feature requests, and documentation problems** go through a [GitHub issue](https://github.com/Maildun/maildun/issues/new/choose). Pick the matching template.
- **Security vulnerabilities** go through a [private GitHub Security Advisory](https://github.com/Maildun/maildun/security/advisories/new), never a public issue. See [SECURITY.md](SECURITY.md).
- **Pull requests** are only accepted when the maintainer has asked for one in an issue thread. A pull request that arrives without that agreement will be closed with thanks, and the underlying issue kept open so the report is not lost.
- Triage is manual, so a reply may take a few days. A comment or label on the issue tells you where it stands.

This is a capacity decision, not a closed door. The policy will be relaxed as the project stabilises.

## Before opening an issue

- Search existing issues first. Add to an existing thread instead of opening a duplicate.
- Reproduce against the latest `main` branch or release when you can.
- Never include credentials, API keys, subscriber data, or production email addresses. Redact logs before pasting them.

## Writing a useful issue

For a **bug**:

- the release tag or output of `git rev-parse --short HEAD`;
- the environment (local, self-hosted production, CI) and the mail provider in use;
- exact steps to reproduce, what you expected, and what happened instead;
- relevant log lines from `storage/logs`, Horizon, or the browser console, with secrets removed.

For a **feature request**:

- the problem you are trying to solve and who it affects;
- how you work around it today, if at all;
- what you would expect the change to look like, without prescribing the implementation.

Focused, reproducible reports are the fastest path to a fix.

## If you were asked for a pull request

When the maintainer asks for a pull request in an issue thread, follow the steps below.

### Development setup

Follow the [installation guide](docs/installation.md), then create a branch from the latest `main` branch.

The application uses the conventions in `AGENTS.md` and `.ai/rules`. Read the rules that cover the files you change. Reuse established components and patterns before adding new abstractions. Keep the change focused; avoid unrelated formatting, dependency, or generated-file changes.

### Tests and code quality

Every behavior change needs a focused Pest test. Run the narrowest relevant test while developing:

```bash
php artisan test --compact tests/Feature/RelevantTest.php
```

Before opening the pull request, run:

```bash
composer ci:check
composer audit --locked
pnpm audit --audit-level=high
```

If you changed PHP, format it with:

```bash
vendor/bin/pint --dirty --format agent
```

Do not commit `.env`, credentials, API keys, database files, generated frontend assets, uploaded media, or licensed icon packages.

### What the pull request should contain

- a link to the issue where the change was agreed;
- the problem and the chosen solution;
- tests for changed behavior and important failure modes;
- notes on migrations, environment variables, queue changes, or deployment steps;
- updates to user-facing documentation when behavior changes; and
- a passing continuous integration run.

## Licensing of contributions

Maildun is licensed under the [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-only). By submitting a pull request, you agree that your contribution is licensed under the same terms (inbound = outbound), and that you have the right to license it that way.

Do not paste code from projects under an incompatible license. Permissively licensed code (MIT, BSD, Apache-2.0) may be incorporated when its notices are preserved and the addition is documented in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
