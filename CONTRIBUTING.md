# Contributing to Maildun

Thank you for helping improve Maildun. Bug reports, documentation fixes, tests, and focused pull requests are welcome.

## Before you start

- Search existing issues and pull requests before opening a duplicate.
- Use a GitHub Security Advisory instead of a public issue for vulnerabilities; see [SECURITY.md](SECURITY.md).
- Discuss large features or architectural changes in an issue before investing in an implementation.
- Keep changes focused. Avoid unrelated formatting, dependency, or generated-file changes.

## Development setup

Follow the [installation guide](docs/installation.md), then create a branch from the latest `main` branch.

The application uses the conventions in `AGENTS.md` and `.ai/rules`. Read the rules that cover the files you change. Reuse established components and patterns before adding new abstractions.

## Tests and code quality

Every behavior change needs a focused Pest test. Run the narrowest relevant test while developing:

```bash
php artisan test --compact tests/Feature/RelevantTest.php
```

Before opening a pull request, run:

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

## Pull requests

A useful pull request:

- explains the problem and the chosen solution;
- links the related issue when one exists;
- includes tests for changed behavior and important failure modes;
- notes migrations, environment variables, queue changes, or deployment steps;
- updates user-facing documentation when behavior changes; and
- passes continuous integration.

## Licensing of contributions

Maildun is licensed under the [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-only). By submitting a pull request, you agree that your contribution is licensed under the same terms (inbound = outbound), and that you have the right to license it that way.

Do not paste code from projects under an incompatible license. Permissively licensed code (MIT, BSD, Apache-2.0) may be incorporated when its notices are preserved and the addition is documented in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
