#!/bin/bash
set -e

# Create necessary directories
mkdir -p /var/log/nginx /var/log/php /run/nginx /run/php
chown -R www-data:www-data /var/log/nginx /var/log/php /run/nginx /run/php
chmod -R 775 /var/log/nginx /var/log/php /run/nginx /run/php

# Set permissions for Laravel directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure the storage/app/public directory exists
mkdir -p /var/www/html/storage/app/public/reportes
chown -R www-data:www-data /var/www/html/storage/app/public
chmod -R 775 /var/www/html/storage/app/public

# Wait for database to be ready
echo "=== Starting database connection check ==="
max_retries=30
counter=0

# Mostrar información de conexión
echo "DB_HOST: $DB_HOST"
echo "DB_PORT: $DB_PORT"
echo "DB_DATABASE: $DB_DATABASE"
echo "DB_USERNAME: $DB_USERNAME"

# Check if database is ready
while [ $counter -lt $max_retries ]; do
    # Verificar si podemos conectar con las credenciales
    if php -r "
    try {
        \$pdo = new PDO(
            'mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ]
        );
        echo '✓ Successfully connected to database' . PHP_EOL;
        exit(0);
    } catch (PDOException \$e) {
        echo '✗ Database connection failed: ' . \$e->getMessage() . PHP_EOL;
        exit(1);
    }
    "; then
        echo "✓ Database connection successful!"

# Configurar caché
if [ "$CACHE_DRIVER" = "database" ]; then
    echo "Configurando caché en base de datos..."
    php artisan cache:table
    php artisan migrate --force
else
    echo "Configurando caché en archivos..."
    php artisan config:clear
fi

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
        break
    fi
  
    counter=$((counter+1))
    if [ $counter -ge $max_retries ]; then
        echo "✗ Max retries reached. Could not connect to the database."
        echo "=== Debug Information ==="
        echo "DB_HOST: $DB_HOST"
        echo "DB_PORT: $DB_PORT"
        echo "DB_DATABASE: $DB_DATABASE"
        echo "DB_USERNAME: $DB_USERNAME"
        exit 1
    fi
    
    echo "Database is unavailable - sleeping (attempt $counter/$max_retries)"
    sleep 2
done

echo "=== Database connection check completed ==="

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Crear enlace simbólico para el almacenamiento
php artisan storage:link

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