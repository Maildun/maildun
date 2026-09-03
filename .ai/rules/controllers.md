---
paths:
  - 'app/Http/Controllers/*Audience*.php'
  - app/Http/Controllers/SegmentController.php
  - 'app/Http/Controllers/{EmailController,EmailTemplateController,TransactionalEmailController,AudienceController,MediaController}.php'
---

# Controllers

## Keep audience data team scoped and public signup opaque
Authenticated audience operations must remain nested beneath {current_team} with scoped parent bindings and policy checks; Members are view-only. Public form submissions must stay generic and idempotent so they never reveal whether an email already exists.

## Segment subscriber payload must match the audience one
segments/show and audiences/show both render SubscriberHoverCard, so SegmentController@show must emit the same subscriber shape as AudienceController@show — including `tags` (eager loaded via `tags:id,uuid,name,color`). The TS type declares `tags: Tag[]` as required, so a missing key is a hard crash ("Cannot read properties of undefined (reading 'length')"), not a degraded card. Covered by the tags assertions in "segment page shows the materialized subscriber list".

## Audience settings updates are partial
PATCH audiences.update only validates and writes the fields that were sent. A sender or landing-pages save must not require name and must not null out other settings. Old ?tab=attributes and ?tab=landing-pages URLs redirect to the dedicated settings routes.

## Index filters are normalized in a private indexFilters()
List controllers read query filters through a private indexFilters(Request) (subscriberFilters() on AudienceController@show) that always returns every key with a default, and pass that array back as the `filters` prop. Unknown values fall back to the neutral default ('' on media/templates/campaigns/transactional, 'all' for subscriber status/source) via Enum::tryFrom or an in_array whitelist, so a hand-typed query string can never filter on junk.

Filtered paginators need ->withQueryString() so page 2 keeps the filters. Campaign status is special: 'sent' matches EmailStatus::Sent OR a Draft with sent_at set, and 'draft' means Draft with sent_at null — the index badge derives from sent_at the same way.
