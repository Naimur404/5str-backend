# syntax=docker/dockerfile:1

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
# Stage 2 — Frontend assets (Vite build)
# ============================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ============================================================
# Stage 3 — Final runtime image
# ============================================================
FROM php:8.4-fpm-alpine AS app

WORKDIR /var/www/html

# --- System packages & PHP extensions -----------------------
RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        oniguruma \
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
    && rm -rf /tmp/pear

# --- Container configuration ---------------------------------
COPY docker/php.ini        /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf     /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh  /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Nginx runs its workers as www-data, so its temp/cache/log directories must
# be writable by that user — otherwise large FastCGI responses that spill to
# disk fail with "Permission denied" and get truncated.
RUN mkdir -p /var/lib/nginx/tmp /var/log/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx

# --- Application code ----------------------------------------
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# Writable directories Laravel needs at runtime.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
