FROM php:8.2-fpm-alpine

# Install php extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install exact extensions matching local dev environment
RUN install-php-extensions \
    intl \
    pdo_mysql \
    mbstring \
    xml \
    ctype \
    iconv \
    zip \
    opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install utilities
RUN apk add --no-cache bash netcat-openbsd

WORKDIR /var/www/html

# Copy app files
COPY . .

# Run Composer install for production
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Permissions
RUN mkdir -p /var/www/html/var && chown -R www-data:www-data /var/www/html/var /var/www/html/public

# Entrypoint
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
