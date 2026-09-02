FROM node:20-alpine AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS dependencies

WORKDIR /var/www/html

COPY composer.json composer.lock ./
# GD and SNMP are compiled in the runtime stage below. The Composer image is
# used only to produce vendor/, so it cannot validate extensions it does not ship.
RUN composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --optimize-autoloader \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-snmp

FROM php:8.2-fpm-bookworm AS runtime

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsnmp-dev \
        libxml2-dev \
        libzip-dev \
        iputils-ping \
        pkg-config \
        util-linux \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring pcntl pdo_pgsql snmp xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-starnms.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-starnms.conf

COPY --chown=www-data:www-data . .
COPY --from=dependencies --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build ./public/build
COPY --chmod=755 docker/scripts/app-entrypoint.sh /usr/local/bin/starnms-entrypoint
COPY --chmod=755 docker/scripts/worker-entrypoint.sh /usr/local/bin/worker-entrypoint

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["starnms-entrypoint"]
CMD ["php-fpm"]
