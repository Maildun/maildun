---
paths:
  - '{composer.json,composer.lock,README.md,docs/installation.md,.github/workflows/*.yml}'
  - '{package.json,pnpm-lock.yaml,.github/workflows/*.yml,README.md}'
---

# Workflows

## PHP 8.4 is the real floor — do not advertise 8.3
`composer.json` requires `php: ^8.4` and CI matrixes 8.4 + 8.5. The floor is 8.4, not 8.3, because the locked tree cannot resolve lower:

- 21 prod `symfony/*` packages (pulled by `laravel/framework`) require `php >= 8.4.1`
- `pestphp/pest` v5 and `phpunit/phpunit` 13 require `php ^8.4` — Pest 5 has no 8.3-compatible release at all

`laravel/framework` itself allows `^8.3` and `symfony/* ^7.4 || ^8.0`, which makes 8.3 look reachable. It is not, unless you downgrade Pest 5→4 and pin Symfony to 7.4 — a dependency change needing approval.

If you ever edit `require.php`, refresh the lock with `composer update --lock --no-scripts` (touches only `content-hash` and `platform`), or `composer validate` fails in CI.

## pnpm audit runs at --audit-level=high
CI runs `pnpm audit --audit-level=high`. The one known moderate advisory, GHSA-w455-mfq9-hf74 (ReDoS in `insane`, reached through the former vendored Waypoint editor), no longer applies since the editor moved to `@maildun/email-builder`.

## Audit fixes go in pnpm-workspace.yaml
Raise vulnerable transitive packages with a narrow `overrides` entry in `pnpm-workspace.yaml` (`pkg@<vulnerable range>: ^<first patched>`, with a comment naming the advisory and the path), then run `pnpm install` and check the lockfile diff touches only those packages.

The only ignored advisory is GHSA-vfj7-8cjw-p6xm (braces <=3.0.3, no patched release on npm), listed under `audit.ignore` in `pnpm-workspace.yaml` so local `pnpm audit` and CI agree. braces is reached only through the shadcn CLI (fast-glob > micromatch), which is development tooling and never bundled. Remove the entry once braces 3.0.4 ships; do not ignore other advisories without the same justification here and in SECURITY.md "Known issues".

