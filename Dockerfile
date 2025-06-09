# Usa una imagen base ligera
FROM php:8.2-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql gd zip pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files first
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-scripts
RUN php artisan package:discover --ansi

# Runtime image
FROM php:8.2-fpm-alpine

# Install runtime dependencies and network tools
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    bash \
    iputils \
    netcat-openbsd \
    mysql-client \
    curl \
    procps \
    busybox-extras \
    tcpdump \
    bind-tools

# Copy PHP extensions from builder
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Copy application files from builder
COPY --from=builder /var/www/html /var/www/html

# Copy configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start.sh /usr/local/bin/start.sh

# Set working directory
WORKDIR /var/www/html

# Create necessary directories and set permissions
RUN mkdir -p /var/log/nginx /var/log/php /run/nginx /run/php \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod +x /usr/local/bin/start.sh

# Expose port 8000 for web traffic
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=3s \
  CMD curl -f http://localhost:8000/health || exit 1

# Start script
CMD ["/usr/local/bin/start.sh"]