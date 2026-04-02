#!/bin/bash
set -e

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache

# Start Apache
apache2-foreground