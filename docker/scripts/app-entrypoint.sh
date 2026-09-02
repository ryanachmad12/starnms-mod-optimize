#!/bin/sh
set -eu

mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
# `storage/id.svg` is a read-only bind mount. Only Laravel's runtime paths need
# write ownership; recursively chowning storage would fail on that asset.
chown -R www-data:www-data storage/app storage/framework storage/logs bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    setpriv --reuid=www-data --regid=www-data --init-groups php artisan migrate --force --no-interaction
fi

if [ "$1" = "php-fpm" ]; then
    # PHP-FPM must initialize its master process as root before it drops each
    # request worker to www-data according to www.conf.
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
