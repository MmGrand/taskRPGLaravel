#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

php artisan migrate --force
# Reference data: the item catalog and starter kits (safe to run on every start)
php artisan db:seed --class=ItemCatalogSeeder --force
# A ready demo hero for reviewers; an existing demo account is left as is. SEED_DEMO=false turns it off
if [ "${SEED_DEMO:-true}" != "false" ]; then
    php artisan db:seed --class=DemoSeeder --force
fi

# php-fpm runs as www-data, but on a Linux host the bind-mounted code belongs to the host user, and the
# commands above run as root: logs, compiled views and caches would not be writable for the app
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
