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
echo "=== Starting database connection check ==="
max_retries=30
counter=0

# Mostrar información de conexión (sin contraseña)
echo "DB_HOST: $DB_HOST"
echo "DB_PORT: $DB_PORT"
echo "DB_DATABASE: $DB_DATABASE"
echo "DB_USERNAME: $DB_USERNAME"

# Check if database is ready
while [ $counter -lt $max_retries ]; do
  # Verificar si podemos hacer ping al host de la base de datos
  if ping -c 1 $DB_HOST &> /dev/null; then
    echo "✓ Host $DB_HOST is reachable"
    
    # Verificar si podemos conectarnos al puerto de MySQL
    if nc -z -w 5 $DB_HOST $DB_PORT; then
      echo "✓ Port $DB_PORT is open on $DB_HOST"
      
      # Verificar si podemos conectar con las credenciales
      if php -r "
      try {
          \$pdo = new PDO(
              'mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),
              getenv('DB_USERNAME'),
              getenv('DB_PASSWORD'),
              [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
          );
          echo '✓ Successfully connected to database' . PHP_EOL;
          exit(0);
      } catch (PDOException \$e) {
          echo '✗ Database connection failed: ' . \$e->getMessage() . PHP_EOL;
          exit(1);
      }
      "; then
        # Si llegamos aquí, la conexión fue exitosa
        echo "✓ Database connection successful!"
        break
      fi
    else
      echo "✗ Port $DB_PORT is not open on $DB_HOST"
    fi
  else
    echo "✗ Cannot reach database host: $DB_HOST"
  fi
  
  counter=$((counter+1))
  >&2 echo "Database is unavailable - sleeping (attempt $counter/$max_retries)"
  sleep 2
  
  if [ $counter -ge $max_retries ]; then
    >&2 echo "✗ Max retries reached. Database is still not available."
    >&2 echo "=== Debug Information ==="
    >&2 echo "DB_HOST: $DB_HOST"
    >&2 echo "DB_PORT: $DB_PORT"
    >&2 echo "DB_DATABASE: $DB_DATABASE"
    >&2 echo "DB_USERNAME: $DB_USERNAME"
    >&2 echo "Current directory: $(pwd)"
    >&2 echo "Environment variables:"
    >&2 printenv | grep DB_ || echo "No DB_* environment variables found"
    exit 1
  fi
done

echo "=== Database connection check completed ==="

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