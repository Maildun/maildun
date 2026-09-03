---
paths:
  - 'database/seeders/**'
  - database/seeders/FullDemoSeeder.php
---

# Seeders

## Seed campaign insights through tracking events
The full demo campaign must create realistic immutable EmailTrackingEvent rows and process them through ProcessEmailTrackingEvent so delivery, basic aggregate, and Campaign Insights totals stay aligned. Reruns may reset only that demo campaign's synthetic tracking rows before recreating them; never seed insight aggregate counts directly.

## Demo data has no personal identity or fixed credential
Demo seeders reuse the oldest existing administrator. On an empty database, AdminUserSeeder creates admin@example.com with a random one-time password printed once. Never restore a personal email address or a fixed demo password; production baseline data remains InstallSeeder-only.

## Audience demo data is idempotent and team-scoped
AudienceSeeder and SubscriberSeeder fill the oldest administrator's personal team with named audiences and a mixed subscriber list. They are idempotent: firstOrCreate on names and skip subscribers when an audience already has any. Call them after AdminUserSeeder and do not mute model events because UUIDs are assigned on creating.
