#!/bin/bash
set -e

# Run migrations in background after a short delay
(
  sleep 10
  echo "Running migrations..."
  php artisan migrate --force
  php artisan config:cache
  php artisan route:cache
  echo "Done."
) &

# Start Apache immediately so the port opens
echo "Starting Apache..."
apache2-foreground