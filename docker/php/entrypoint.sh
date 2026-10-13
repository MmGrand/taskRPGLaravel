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

exec "$@"
