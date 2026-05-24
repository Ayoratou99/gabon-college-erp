# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-alpine AS base

ARG WWWUSER=1000
ARG WWWGROUP=1000

# System deps + build deps + runtime libs
RUN apk add --no-cache \
        bash git curl icu-libs libpq libzip oniguruma libxml2 freetype libjpeg-turbo libpng \
        $PHPIZE_DEPS \
        icu-dev libpq-dev libzip-dev oniguruma-dev libxml2-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql intl bcmath zip mbstring opcache pcntl gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge $PHPIZE_DEPS icu-dev libpq-dev libzip-dev oniguruma-dev libxml2-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && rm -rf /var/cache/apk/*

# Composer (multi-stage to keep image small)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom php.ini overrides
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-cuk.ini

# Create non-root user matching host UID/GID for sane file permissions
RUN addgroup -g ${WWWGROUP} cuk \
    && adduser -D -u ${WWWUSER} -G cuk -h /home/cuk -s /bin/bash cuk \
    && mkdir -p /var/www/html /home/cuk/.composer \
    && chown -R cuk:cuk /var/www/html /home/cuk

USER cuk
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm", "-F"]
