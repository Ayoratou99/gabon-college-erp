# syntax=docker/dockerfile:1.7
#
# Multi-stage build — every code change is baked into the image:
#
#   stage `vendor` : composer deps  (PHP only, no scripts/autoload yet)
#   stage `assets` : npm + `vite build`  → public/build/*
#   stage `app`    : php-fpm runtime containing source + vendor + assets
#   stage `nginx`  : nginx 1.27 containing only `public/`
#
# Each rebuild:  docker compose up -d --build
# No bind-mounts at runtime — the host filesystem isn't traversed by PHP-FPM
# on every request (this is the slow-on-Windows path we're getting rid of).
# ---------------------------------------------------------------------------

ARG PHP_VERSION=8.4

# === stage 1 — composer deps ================================================
FROM composer:2 AS vendor

WORKDIR /app

# Copy only the manifest first so this layer caches across source edits.
COPY composer.json composer.lock ./

# `--no-scripts --no-autoloader` because the source isn't here yet; the
# final autoload dump happens in the `app` stage once code is present.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs


# === stage 2 — JS / CSS bundle =============================================
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Everything Vite needs at build time:
COPY vite.config.js ./
COPY resources resources
# Vite scans Modules/**/resources/views/** for the dev-server refresh hint —
# the build itself doesn't need them, but copying keeps the config valid.
COPY Modules Modules

RUN npm run build


# === stage 3 — PHP-FPM runtime ============================================
FROM php:${PHP_VERSION}-fpm-alpine AS app

ARG WWWUSER=1000
ARG WWWGROUP=1000

# System deps + PHP extensions. Build deps are dropped at the end of the
# RUN to keep the runtime image lean.
RUN apk add --no-cache \
        bash git curl icu-libs libpq libzip oniguruma libxml2 \
        freetype libjpeg-turbo libpng \
        $PHPIZE_DEPS \
        icu-dev libpq-dev libzip-dev oniguruma-dev libxml2-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql intl bcmath zip mbstring opcache pcntl gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge $PHPIZE_DEPS icu-dev libpq-dev libzip-dev oniguruma-dev libxml2-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && rm -rf /var/cache/apk/*

# Composer binary (used for autoload dump + ad-hoc maintenance inside the
# running container; runtime requests never touch it).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-cuk.ini

RUN addgroup -g ${WWWGROUP} cuk \
    && adduser -D -u ${WWWUSER} -G cuk -h /home/cuk -s /bin/bash cuk \
    && mkdir -p /var/www/html /home/cuk/.composer \
    && chown -R cuk:cuk /var/www/html /home/cuk

WORKDIR /var/www/html

# Bring in the prebuilt vendor + asset bundle FIRST so source edits don't
# re-install / re-build them on every change.
COPY --chown=cuk:cuk --from=vendor /app/vendor ./vendor
COPY --chown=cuk:cuk --from=assets /app/public/build ./public/build

# Then the application source.
COPY --chown=cuk:cuk . .

# Final autoload dump now that source + module providers are present.
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                storage/app/public \
                storage/app/private \
                bootstrap/cache \
    && chown -R cuk:cuk storage bootstrap/cache

USER cuk

EXPOSE 9000
CMD ["php-fpm", "-F"]


# === stage 4 — nginx serving public/ =======================================
FROM nginx:1.27-alpine AS nginx

# Copy the application's public/ tree from the app stage so nginx can serve
# the compiled static assets (manifest.json, public/build/*) without any
# bind mount or shared volume.
COPY --from=app /var/www/html/public /var/www/html/public

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
