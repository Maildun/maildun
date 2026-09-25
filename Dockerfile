# syntax=docker/dockerfile:1.7

# Maildun runtime image.
#
# One image serves all three long-running processes. docker-compose.yml starts
# it three times with different commands: the web server, Horizon, and the
# scheduler. Building once keeps their code and dependencies identical, which
# matters because a queue worker running older code than the web process fails
# in ways that are hard to read.

ARG PHP_VERSION=8.4


########################################
# Base: the PHP platform every stage shares
########################################
# Dependency resolution, the asset build, and the runtime all derive from this
# single stage so Composer validates platform requirements against exactly the
# extension set the application will run on. Resolving against a leaner image
# is how you end up shipping a lock file the runtime cannot satisfy.
#
# pcntl and posix are what Horizon uses to supervise and signal its workers,
# and laravel/horizon declares ext-pcntl, so dependency resolution fails
# without it. gd carries the WebP encoder that
# App\Actions\Media\ConvertImageToWebp calls. redis is phpredis, which
# config/database.php selects as the Redis client.
FROM dunglas/frankenphp:1-php${PHP_VERSION} AS base

RUN install-php-extensions \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        redis \
        zip

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app


########################################
# 1. PHP dependencies
########################################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./

# Resolve dependencies before the source lands so this layer survives ordinary
# code edits. --no-scripts and --no-autoloader because post-autoload-dump runs
# artisan, which needs application code that is not here yet.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --no-progress

# The classmap has to be built where both Composer and the source exist. The
# runtime image ships neither Composer nor a package manager, so it cannot
# generate this itself.
#
# --optimize without --classmap-authoritative: the classmap still resolves
# every application class directly, but PSR-4 stays available as a fallback
# rather than turning a missed entry into a fatal "class not found".
#
# Package discovery is deliberately left to first boot, where the entrypoint's
# config:cache performs it and writes the manifest to bootstrap/cache.
COPY . .
RUN composer dump-autoload --no-dev --optimize


########################################
# 2. Frontend assets
########################################
# This stage needs Node as well as PHP: vite.config.ts runs the wayfinder
# plugin, which shells out to `php artisan wayfinder:generate` to emit the
# typed route helpers that resources/js imports, and those directories are not
# committed. A Node-only stage cannot build this application.
FROM base AS assets

# Both images are Debian bookworm, so Node's prefix transplants cleanly and
# pins the version without adding a third-party apt repository.
COPY --from=node:22-bookworm-slim /usr/local/ /usr/local/

# The Node image's yarn symlinks resolve outside /usr/local, so copying that
# prefix alone leaves them dangling. `corepack enable` calls realpath over
# every shim it manages and dies on them. Drop the links, and enable only
# pnpm, which is the package manager this project declares.
RUN rm -f /usr/local/bin/yarn /usr/local/bin/yarnpkg \
    && corepack enable pnpm \
    && pnpm --version

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN --mount=type=cache,target=/pnpm-store \
    pnpm config set store-dir /pnpm-store \
    && pnpm install --frozen-lockfile

COPY --from=vendor /app/vendor ./vendor
COPY . .

# .dockerignore keeps local runtime state out of the context, so these are
# absent here. Booting artisan without them fails while resolving the log and
# cache paths, which is what the wayfinder plugin runs into.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

# Generating the helpers explicitly, with the same --with-form the vite plugin
# passes, is purely for legibility: the plugin reports a failure here as
# "Command failed" with the underlying artisan error discarded. The build
# regenerates them immediately afterwards, so this cannot drift from what the
# plugin produces. Passing the bare command without --with-form would omit the
# form variants and break type-checking in the pages that import them.
#
# A throwaway key satisfies the bootstrap. It is never written to the image:
# this stage contributes nothing but public/build to the final layer. Wayfinder
# reads routes only and never opens a database connection.
RUN export APP_KEY=base64:$(head -c 32 /dev/urandom | base64) \
    && php artisan wayfinder:generate --with-form \
    && pnpm run build


########################################
# 3. Runtime
########################################
FROM base AS runtime

# The code belongs to root, so the server cannot change it; only the
# directories Laravel writes to belong to www-data. A writable application
# root would also let app:install create a .env from .env.example underneath
# an environment the platform manages (Coolify, Managed WP).
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Directories Laravel writes to at runtime. docker-compose.yml mounts a volume
# over storage/, so these are the fallback for a plain `docker run`. The
# storage:link symlink is made here too, because public/ is read-only at
# runtime; it serves uploads on the local disk (FILESYSTEM_DISK=local).
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && ln -s /app/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

# The entrypoint locks with flock and the healthcheck requests with curl.
RUN command -v flock curl

COPY <<'PHPINI' /usr/local/etc/php/conf.d/maildun.ini
; Campaign HTML and contact imports both exceed the stock 2M ceiling.
upload_max_filesize = 32M
post_max_size = 32M
memory_limit = 512M

opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.interned_strings_buffer = 16
PHPINI

COPY <<'ENTRYPOINT' /usr/local/bin/maildun-entrypoint
#!/bin/sh
set -e

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set." >&2
    echo "Generate one with: docker compose run --rm --entrypoint php app artisan key:generate --show" >&2
    echo "It encrypts stored workspace mail credentials and automation tokens." >&2
    echo "Losing or changing it makes existing encrypted values unreadable." >&2
    exit 1
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Only the web service sets this. Running migrations from three services that
# start together races them against each other.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Passport signs API tokens with these. They live under storage/, which is a
# volume, so they survive restarts; regenerating them invalidates every token.
# The web server, Horizon and the scheduler share that volume and start
# together, so the lock lets exactly one of them create the pair.
flock storage/.passport-keys.lock sh -c \
    '[ -f storage/oauth-private.key ] || php artisan passport:keys --no-interaction || true'

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
ENTRYPOINT

RUN chmod +x /usr/local/bin/maildun-entrypoint

# Binding above 1024 keeps the server off root and out of needing
# CAP_NET_BIND_SERVICE. Publish it as :80 on the host in compose.
ENV SERVER_NAME=:8080

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["maildun-entrypoint"]
CMD ["frankenphp", "php-server", "--root", "public/", "--listen", ":8080"]
