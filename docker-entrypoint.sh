#!/bin/bash
set -e

# wait for database
echo "Waiting for database..."
until php artisan migrate:status > /dev/null 2>&1; do
  sleep 3
done

echo "Database ready"

# run migrations
php artisan migrate --force

php artisan config:cache
php artisan route:cache

apache2-foreground