# syntax=docker/dockerfile:1.7

# ============================================================
# 5SRT Backend — Laravel 13 / PHP 8.4 (Coolify-ready)
#
# Build stages:
#   1. vendor — composer dependencies + optimized autoloader
#   2. app    — runtime image (php-fpm + nginx + supervisord)
#
# The image runs everything inside one container under supervisord:
#   php-fpm, nginx, horizon, scheduler, cache-invalidation-listener.
# ============================================================

# ============================================================
# Stage 1 — Composer dependencies
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

# Install PHP deps without running scripts (artisan isn't ready yet).
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --ignore-platform-reqs

# Copy the full source and build the optimized autoloader.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ============================================================
# Stage 2 — Final runtime image
# ============================================================
FROM php:8.4-fpm-alpine AS app

LABEL org.opencontainers.image.source="https://github.com/your-org/5str-backend"
LABEL org.opencontainers.image.description="5SRT API — Laravel 13 / PHP 8.4 / phpredis / Meilisearch (Scout)"

WORKDIR /var/www/html

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=30000 \
    COMPOSER_ALLOW_SUPERUSER=1

# --- System packages & PHP extensions -----------------------
# pdo_mysql / mbstring / bcmath / gd / zip / exif / pcntl / intl / opcache
# come from PHP itself; phpredis is built via PECL.
RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        tini \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        oniguruma \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        exif \
        pcntl \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

# --- Container configuration ---------------------------------
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Nginx runs its workers as www-data, so its temp/cache/log directories must
# be writable by that user — otherwise large FastCGI responses that spill to
# disk fail with "Permission denied" and get truncated.
RUN mkdir -p /var/lib/nginx/tmp /var/log/nginx /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx

# --- Application code ----------------------------------------
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor

# Writable directories Laravel needs at runtime.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# --- Health probe used by Coolify / k8s ----------------------
# The app exposes /api/healthz (liveness) and /api/readyz (readiness).
# Coolify reads HEALTHCHECK from the image automatically.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/api/healthz || exit 1

EXPOSE 80

# tini reaps zombie children — keeps supervisord/php-fpm graceful on SIGTERM.
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
