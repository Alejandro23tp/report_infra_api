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

# Forzar caché en archivos
echo "Configurando caché en archivos..."
# Crear archivo de configuración temporal para forzar caché en archivos
cat > /var/www/html/config/cache.php << 'EOL'
<?php

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],
    'prefix' => 'laravel_cache',
];
EOL

# Limpiar caché
echo "Limpiando caché..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
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

# Run migrations with better error handling
echo "=== Initializing migrations ==="

# Verificar y configurar la tabla de migraciones
echo "=== Checking migrations table ==="

# Verificar si la tabla de migraciones existe
if php artisan migrate:status | grep -q 'Migration table not found'; then
    echo "ℹ️ Migrations table does not exist. Creating..."
    if ! php artisan migrate:install; then
        echo "❌ Failed to create migrations table"
        exit 1
    fi
    echo "✅ Migrations table created successfully"
else
    echo "✅ Migrations table exists"
    
    # Mostrar información de migraciones
    echo -e "\n=== Migration Status ==="
    php artisan migrate:status
fi

echo "=== Running migrations ==="
if ! php artisan migrate --force; then
    echo "\n❌ Error: Migrations failed"
    echo "\n=== Migration status ==="
    php artisan migrate:status || true
    
    echo "\n=== Database info ==="
    php -r "
    require __DIR__.'/vendor/autoload.php';
    \$app = require_once __DIR__.'/bootstrap/app.php';
    \$kernel = \$app->make(Illinewline\Contracts\Console\Kernel::class);
    \$kernel->bootstrap();
    
    try {
        // Mostrar información de la base de datos
        \$pdo = \DB::connection()->getPdo();
        echo 'Database: ' . \$pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
        
        // Listar tablas
        \$tables = \DB::select('SHOW TABLES');
        echo '\nTables in database (' . count(\$tables) . '):\n';
        foreach (\$tables as \$table) {
            echo '- ' . array_values((array)\$table)[0] . "\n";
        }
        
        // Verificar tabla de migraciones
        if (\Schema::hasTable('migrations')) {
            echo "\n✅ Migrations table exists\n";
            \$migrations = \DB::table('migrations')->count();
            echo "Migrations count: \$migrations\n";
            
            // Mostrar migraciones fallidas si las hay
            \$failed = \DB::table('migrations')->where('batch', '>', 1)->count();
            if (\$failed > 0) {
                echo "\n❌ Found \$failed failed migrations\n";
                \$failedMigrations = \DB::table('migrations')
                    ->where('batch', '>', 1)
                    ->pluck('migration')
                    ->toArray();
                echo "Failed migrations: " . implode(', ', \$failedMigrations) . "\n";
            }
        } else {
            echo "\n❌ Migrations table does not exist\n";
        }
        
    } catch (Exception \$e) {
        echo '❌ Error: ' . \$e->getMessage() . "\n";
    }"
    
    echo "\n=== Attempting to fix migrations ==="
    php artisan migrate:install
    
    echo "\n=== Retrying migrations ==="
    if ! php artisan migrate --force; then
        echo "\n❌ Critical error: Could not run migrations after retry"
        
        echo "\n=== Attempting database refresh ==="
        if php artisan db:refresh-without-checks --force; then
            echo "\n✅ Database refreshed successfully"
        else
            echo "\n❌ Failed to refresh database"
            echo "\n=== Last 50 lines of error log ==="
            tail -n 50 storage/logs/laravel.log || echo "No se pudo leer el archivo de log"
            exit 1
        fi
    fi
fi

echo "\n✅ Migrations completed successfully"
echo "=== Final migration status ==="
php artisan migrate:status

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