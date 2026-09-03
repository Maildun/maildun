---
paths:
  - '{app/Console/Commands/InstallCommand.php,database/seeders/InstallSeeder.php,database/seeders/DatabaseSeeder.php,app/Services/EnvironmentFile.php}'
---

# Seeders Services

## app:install is the deploy entry point; InstallSeeder is the only production-safe seeder
`php artisan app:install` is what `composer setup` and a Forge/Cloud deploy script run: .env, app key, migrations, InstallSeeder, sign-up policy, first administrator, storage. Every step is idempotent so it converges on each deploy, and every prompt has a matching option so `--no-interaction --force` works headless.

DatabaseSeeder seeds demo campaigns and subscribers and must never touch a real install; InstallSeeder is the production baseline (global TeamPermission rows, cache flushed either side) and is what app:install calls. Demo data is only reachable through `--demo`, which refuses production without --force.

Writes go through App\Services\EnvironmentFile (shared with storage:configure). Laravel Cloud serves .env from its dashboard, so an unwritable file is not an error: the values are collected and printed for the operator instead. If the configuration is cached, app:install re-runs `optimize`, or the freshly written values would never be read.
