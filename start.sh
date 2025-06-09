#!/bin/bash
set -e

# Create necessary directories
mkdir -p /var/log/nginx /var/log/php /run/nginx /run/php
chown -R www-data:www-data /var/log/nginx /var/log/php /run/nginx /run/php
chmod -R 775 /var/log/nginx /var/log/php /run/nginx /run/php

# Set permissions for Laravel directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Wait for database to be ready
echo "Waiting for database..."
max_retries=30
counter=0

# Check if database is ready
until php artisan db:show --env=production > /dev/null 2>&1; do
  >&2 echo "Database is unavailable - sleeping (attempt $counter/$max_retries)"
  sleep 1
  counter=$((counter+1))
  if [ $counter -ge $max_retries ]; then
    >&2 echo "Max retries reached. Database is still not available."
    exit 1
  fi
done

# Run database migrations
echo "Running migrations..."
php artisan migrate --force

# Clear and cache configuration
echo "Caching configuration..."
php artisan config:clear
php artisan config:cache

# Clear and cache routes
echo "Caching routes..."
php artisan route:clear
php artisan route:cache

# Clear and cache views
echo "Caching views..."
php artisan view:clear
php artisan view:cache

# Clear and cache application
echo "Caching application..."
php artisan cache:clear
php artisan optimize

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Start supervisord in the foreground
echo "Starting services..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf