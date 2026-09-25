# Installation

This guide installs Maildun for local development or evaluation. Read [Production deployment](production.md) before exposing it to the internet.

## Requirements

- PHP 8.4 or newer with the extensions required by `composer.lock`
- Composer 2
- Node.js 22 and pnpm 11
- Redis
- SQLite, MySQL, MariaDB, or PostgreSQL
- An application-wide mail relay for system email
- An Amazon SES or SMTP account for each workspace that will send email

The default `.env.example` uses SQLite, Redis queues, Redis cache, and a local SMTP relay on port 2525. That relay is only a development default; it is not suitable for a public installation.

## Configure email delivery

Maildun has two separate delivery scopes:

| Scope | Configuration | Used for |
| --- | --- | --- |
| System | Application-wide `MAIL_*` environment values | Account verification, password resets, workspace invitations, and operational notifications |
| Workspace | **Settings → Email delivery**, then **Settings → Sender** | Campaigns, automations, transactional email, and workspace test sends |

A workspace sender or provider never replaces the system mailer. Account recovery may happen before login and a user may belong to multiple workspaces, so system email must always have one deployment-wide transport and sender.

For local development, start an SMTP catcher on `127.0.0.1:2525` or set `MAIL_MAILER=log` to write messages to the application log. For production, configure a real relay in `.env` or the hosting platform's secret manager before enabling registration or sending invitations:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

Use credentials supplied by the relay and make sure `MAIL_FROM_ADDRESS` is authorized by that provider. Use `MAIL_SCHEME=smtps` with port 465 only when the provider requires implicit TLS. Never commit the populated `.env` file.

After installation, sign in and configure every sending workspace separately. Connect its one Amazon SES or SMTP account under **Settings → Email delivery** and test it with explicit From and To addresses. Then add each allowed From address under **Settings → Sender** and complete its separate verification. Changing or deleting delivery credentials makes all sender proofs stale until they are tested again.

## Automatic setup

```bash
git clone https://github.com/Maildun/maildun.git
cd maildun
cp .env.example .env
# Review the database, Redis, storage, and system MAIL_* values in .env.
touch database/database.sqlite
composer setup
```

Before running `composer setup`, adjust the database, Redis, storage, and system `MAIL_*` values in `.env` when you are not using the local defaults. Start Redis and the configured local mail relay before running the command. The installer will:

1. install PHP dependencies;
2. generate `APP_KEY`;
3. migrate the database and seed required permissions;
4. ask whether registration is open or invitation-only;
5. create the first administrator;
6. configure local or S3-compatible storage;
7. install JavaScript dependencies; and
8. build production frontend assets.

The command is designed to be safe to run again. Demo data is never added unless `--demo` is explicitly passed.

## Docker

The repository ships a `Dockerfile` and a `docker-compose.yml` that start the
web server, Horizon, the scheduler, PostgreSQL, and Redis together. All three
application services run the same image, so their code and dependencies cannot
drift apart.

```bash
cp .env.example .env
docker compose run --rm --entrypoint php app artisan key:generate --show
```

Put that key in `.env` as `APP_KEY`, set `DB_PASSWORD`, and set `APP_URL` to the
address the installation will actually serve. Then start everything and create
the first administrator:

```bash
docker compose up -d
docker compose exec app php artisan app:install --no-interaction \
    --admin-name="Your Name" \
    --admin-email=you@example.com \
    --admin-password='a-strong-password' \
    --registration=closed
```

The application listens on port 80 by default; set `APP_PORT` to change it.
Terminate TLS in front of the container and keep `APP_URL` on `https://`, since
signed tracking and unsubscribe links are built from it.

The `storage` volume holds uploaded media, logs, and the Passport signing keys.
Back it up together with the database and `APP_KEY`. Deleting it invalidates
every API token that has been issued.

Migrations run automatically when the `app` service starts, so a deployment is:

```bash
docker compose build
docker compose up -d
```

Horizon and the scheduler are separate services on purpose. Stopping either one
leaves the web interface working while campaigns silently stop sending, so treat
them as required rather than optional.

## Manual setup

Use this sequence when you want to control each step:

```bash
composer install
pnpm install --frozen-lockfile
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan app:install
pnpm run build
```

Adjust database, Redis, system mail, and storage values in `.env` before `app:install` if you are not using the local defaults. Workspace provider credentials are configured in the application after installation, not in the system `MAIL_*` values.

## Run the application

```bash
composer dev
```

This starts the Laravel development server, Vite, Horizon, and the log viewer. Run scheduled tasks in another terminal when working on automations, segment synchronization, tracking recovery, or retention:

```bash
php artisan schedule:work
```

## Demo data

For an expendable local database only:

```bash
php artisan app:install --demo
```

The installer attaches demo data to the administrator created earlier in the run. If the demo seeder is invoked directly on an empty database, it creates `admin@example.com` with a random one-time password printed to that terminal. It never ships a fixed default credential.

Never run the demo seeder against real data. Production requires `--force` before the installer will allow demo data.

## Storage

Local storage is suitable for a single server:

```bash
php artisan storage:configure --backend=local
```

For Amazon S3, Cloudflare R2, or another compatible service, run:

```bash
php artisan storage:configure --backend=s3
```

The command asks which provider you use and writes separate `AWS_*` or `R2_*` settings. Choose **Something else (MinIO, DigitalOcean Spaces, ...)** for MinIO or another S3-compatible service. It uses the `AWS_*` variables with `OBJECT_STORAGE_PROVIDER=custom`.

### MinIO and other S3-compatible services

Create two buckets before running the installer:

- a private bucket for attachments and pending uploads, such as `maildun-private`; and
- a public bucket for media, logos, and avatars, such as `maildun-public`.

Keep the private bucket private. Expose read-only access to the public bucket through a CDN, a custom domain, or a deliberately public MinIO bucket policy. The public URL must be reachable by every browser that uses Maildun; do not use the MinIO Console URL.

The interactive storage command collects the endpoint, credentials, bucket names, and public URL. For MinIO, also set path-style addressing in `.env`. A local MinIO server might use:

```dotenv
FILESYSTEM_DISK=s3
OBJECT_STORAGE_PROVIDER=custom

AWS_ACCESS_KEY_ID=minio-access-key
AWS_SECRET_ACCESS_KEY=minio-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

AWS_BUCKET=maildun-private
AWS_URL=http://127.0.0.1:9000/maildun-private
AWS_PUBLIC_BUCKET=maildun-public
AWS_PUBLIC_URL=http://127.0.0.1:9000/maildun-public
```

For a cloud-hosted MinIO deployment, replace the local API endpoint and public URL with HTTPS addresses. A CDN or custom domain is recommended for the public bucket:

```dotenv
AWS_ENDPOINT=https://minio.example.com
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=https://minio.example.com/maildun-private
AWS_PUBLIC_URL=https://assets.example.com
```

`AWS_ENDPOINT` is the MinIO S3 API endpoint, not its administration console. If the public bucket is served directly from MinIO, `AWS_PUBLIC_URL` must include the bucket name, as in the local example. Keep `AWS_PUBLIC_URL` free of a trailing slash.

To check the currently configured backend without prompts or `.env` changes, run:

```bash
php artisan storage:check --no-interaction
```

It writes, reads, and removes a temporary object from both storage roles. The command exits with `0` when both probes pass and `1` when configuration or access fails, so it can be used in deployment scripts.

To preview a migration between backends:

```bash
php artisan storage:sync --to=s3 --dry-run
```

## Verify the installation

Keep Horizon running while testing because password resets and workspace invitations are queued. Request a password-reset link for an account you control and confirm that it arrives through the system mailer. Then configure and test a workspace delivery connection, verify a workspace sender through it, and send a small campaign only after both checks succeed.

Run the repository checks:

```bash
composer validate
composer ci:check
composer audit --locked
pnpm audit --audit-level=high
```

If frontend changes do not appear, run `pnpm run build` or keep `composer dev` running.

## Common problems

### Redis connection errors

Redis must be reachable during installation because permissions and application caches are initialized while migrations run. Start Redis or change both `CACHE_STORE` and `QUEUE_CONNECTION` to services available in your environment.

### Messages remain queued

Keep `php artisan horizon` running. A web server alone does not process campaign, transactional, automation, media, or tracking jobs.

### Campaigns send, but password resets or invitations do not

The workspace provider only sends workspace email. Configure the application-wide `MAIL_*` values and ensure the `default` queue is being processed. After changing production environment values, rebuild Laravel's cached configuration and restart Horizon so workers load the new mailer:

```bash
php artisan optimize:clear
php artisan optimize
php artisan horizon:terminate
```

### Scheduled work does not run

Use `php artisan schedule:work` locally. Production should execute `php artisan schedule:run` every minute.
