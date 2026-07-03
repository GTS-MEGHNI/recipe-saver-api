# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Composer dependencies (no autoloader, no scripts).
# Cached independently of application source: this layer only rebuilds when
# composer.json / composer.lock change, keeping app-code edits cheap.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# ---------------------------------------------------------------------------
# Stage 2 — Runtime image (FrankenPHP).
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS runtime

LABEL org.opencontainers.image.source="recipes-api"

# System packages + PHP extensions required by the app:
#   pdo_sqlite  -> SQLite driver
#   gd + exif   -> image optimisation (App\Services\ImageOptimizer)
#   opcache     -> production performance
#   pcntl, zip  -> FrankenPHP / composer support
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        pdo_sqlite \
        gd \
        exif \
        opcache \
        pcntl \
        zip

# Production PHP + OPcache configuration.
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app

# Bring in the already-resolved dependencies first (rarely changes), then the
# application source (changes often) so the extension + vendor layers stay cached.
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Generate the optimised, production autoloader and package manifest now that
# both the source and vendor tree are present.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
