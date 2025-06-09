#!/bin/sh

# Ejecuta migraciones
php artisan migrate --force

# Optimiza la aplicación
php artisan optimize

# Crea el enlace simbólico para el almacenamiento
php artisan storage:link

# Limpia la caché de configuración
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Establece los permisos correctos
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Inicia el servicio PHP-FPM
php-fpm &
# Inicia Nginx
nginx -g 'daemon off;'