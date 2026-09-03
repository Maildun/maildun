---
paths:
  - 'database/migrations/*permission*.php database/migrations/*_grant_*.php'
---

# Migrations

## Flush Spatie's permission cache in migrations that grant permissions via raw SQL
Migrations that insert into `permissions` / `role_has_permissions` with the DB facade bypass Spatie's models, so its cached permission map is never invalidated. The grant appears in the database but `hasTeamPermission()` keeps returning false until the cache expires — this actually happened with `email:manage`.

Always call `app(PermissionRegistrar::class)->forgetCachedPermissions()` at the start and end of both `up()` and `down()`. See `2026_08_16_204833_grant_email_management_permission_to_existing_roles.php`. Note `2026_08_16_112826_grant_audience_management_permission_to_existing_roles.php` predates this rule and does not do it.
