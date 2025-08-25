FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    git \
    curl \
    mysql-client \
    libzip-dev \
    gettext \
    nginx

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist

# Copy Nginx configurations
COPY config/nginx.conf /etc/nginx/nginx.conf
COPY config/nginx.conf.template /etc/nginx/conf.d/default.conf.template

# Change ownership
RUN chown -R www-data:www-data /var/www/html

# Expose port and start servers
CMD ["/bin/sh", "-c", "envsubst '${PORT}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;' & exec php-fpm"]