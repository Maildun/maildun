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

## pnpm audit stays at --audit-level=high (insane ReDoS is unfixable)
`pnpm audit --audit-level=high` reports 1 moderate advisory that will not go away. Do not chase it, and do not lower the threshold to `moderate` — CI would fail permanently.

GHSA-w455-mfq9-hf74: ReDoS in `insane` <=2.6.2, reached via `@usewaypoint/block-text` (and `@usewaypoint/email-builder`). The advisory lists "patched >=2.6.3", but **2.6.2 is the newest version ever published to npm** — the package is abandoned, so no upgrade or `pnpm.overrides` can resolve it.

Reachability is low: `insane` sanitizes HTML inside the email builder editor, in the authenticated author's own browser. It never runs in Node — `config/inertia.php` sets `ssr.enabled => true`, but no SSR entrypoint or bundle exists, so `HttpGateway::dispatch()` short-circuits on `bundleExists()` before making any request.

If it ever needs a real fix, the route is `pnpm.patchedDependencies`, not a version bump.
