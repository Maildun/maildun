---
paths:
  - '{config/fortify.php,app/Http/Middleware/EnsureRegistrationIsOpen.php,app/Providers/FortifyServiceProvider.php}'
---

# Middleware Providers

## Gate registration at request time, never by dropping the Fortify feature
REGISTRATION_ENABLED drives `fortify.registration_open`, which EnsureRegistrationIsOpen (added to `fortify.middleware`, so it only runs on Fortify routes) enforces. Never close registration by removing `Features::registration()` from `fortify.features`: `/resources/js/routes` is gitignored and Wayfinder regenerates it during the deploy build, so a missing `register` route drops the `register` export and `pnpm run build` fails on login.tsx and welcome.tsx. Gating at request time also lets the switch be flipped from a Forge or Laravel Cloud dashboard with no rebuild.

A closed instance must still admit invited people — TeamInvitation::accept requires an authenticated user, so blocking registration outright strands every invite. The middleware matches the GET on `?invitation=<code>` and the POST on the submitted email, because the register form does not post the code back.
