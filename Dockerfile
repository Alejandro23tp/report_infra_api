# Usa una imagen base ligera
FROM php:8.2-fpm-alpine

# Instala dependencias
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    bash \
    procps \
    htop \
    vim \
    curl

# Configura PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql gd zip pcntl

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configura el directorio de trabajo
WORKDIR /var/www/html

# Copia primero solo los archivos necesarios para instalar dependencias
COPY composer.json composer.lock ./

# Instala dependencias (sin dependencias de desarrollo)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copia el resto de los archivos, excluyendo .env si existe
COPY . .

# Crea directorios necesarios
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/run/php \
    && mkdir -p /var/run/nginx \
    && mkdir -p /var/run/supervisor \
    && mkdir -p /var/log/nginx \
    && touch /var/log/nginx/access.log \
    && touch /var/log/nginx/error.log

# Crea directorio de logs de Laravel
RUN mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/worker.log \
    && touch /var/www/html/storage/logs/laravel.log \
    && touch /var/log/php-fpm.log

# Configura los permisos
RUN chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /var/log/php-fpm.log

# Copia la configuración
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# No es necesario generar claves aquí, Render manejará las variables de entorno

# Expone el puerto
EXPOSE 8000

# Comando de inicio
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]