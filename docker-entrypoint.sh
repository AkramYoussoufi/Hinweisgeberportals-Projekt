#!/bin/bash
set -e

# ensure env exists
if [ ! -f .env ]; then
  cp .env.example .env
fi

# ensure key exists
php artisan key:generate --force

echo "Waiting for database..."

until php artisan migrate:status > /dev/null 2>&1; do
  sleep 3
done

echo "Database ready"

php artisan migrate --force

php artisan config:cache
php artisan route:cache

apache2-foreground