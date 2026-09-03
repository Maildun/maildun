---
paths:
  - 'app/Console/Commands/ConfigureStorageCommand.php,app/Enums/StorageBackend.php,app/Services/StorageBackendMigrator.php'
---

# Enums Services

## Storage is picked at install by storage:configure, validated through config not env
`php artisan storage:configure` is the install-time entry point (wired into `composer setup`): it prompts local vs S3/R2, writes .env, round-trips a probe file through both target disks, runs storage:link for local, and offers storage:sync when files sit on the other backend.

Validate required storage settings through config('filesystems.disks.*'), never env(). env() returns null once config:cache has run, which would report a correctly configured production app as unconfigured. StorageBackend::requiredConfiguration() maps each config key to the .env name so errors still tell the operator which line to edit.

AWS_PUBLIC_URL is required only when AWS_ENDPOINT is set — R2/MinIO buckets are not publicly servable, while plain AWS S3 falls back to its own bucket URL.

Tests: the command writes a real .env, so point the app at a temp dir with useEnvironmentPath()/useStoragePath() (Storage::fake does not survive the command's config reload), and restore $_ENV afterwards or the mutations leak into later tests.</note>
</invoke>
