#!/bin/bash
set -e

# Start Apache immediately so Render detects the open port
(
  sleep 10
  echo "Running migrations..."
  php artisan migrate --force
  echo "Running seeders..."
  php artisan db:seed --force
  php artisan config:cache
  php artisan route:cache
  echo "Done."
) &

echo "Starting Apache..."
apache2-foreground