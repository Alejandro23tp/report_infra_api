#!/bin/sh
set -e

# Establecer permisos de almacenamiento
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Crear enlace simbólico de almacenamiento si no existe
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link
fi

# Ejecutar migraciones
php artisan migrate --force

# Limpiar caché
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Optimizar la aplicación
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Crear directorio de colas si no existe
mkdir -p /var/www/html/storage/framework/cache/data
chown -R www-data:www-data /var/www/html/storage/framework/cache/data
chmod -R 775 /var/www/html/storage/framework/cache/data

# Establecer permisos para los logs
touch /var/www/html/storage/logs/laravel.log
chown -R www-data:www-data /var/www/html/storage/logs
chmod -R 775 /var/www/html/storage/logs

# Iniciar supervisord
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf