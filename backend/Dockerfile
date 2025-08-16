FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction || true

COPY . .
RUN composer dump-autoload --optimize || true

RUN chown -R www-data:www-data /var/www/html/var /var/www/html/vendor || true

EXPOSE 9000
CMD [ "php-fpm" ]
