FROM php:8.3-cli

# System dependencies needed by common Laravel/pgsql extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Composer, copied from its official image rather than curl-piped into bash
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

# artisan serve is fine for a portfolio/demo Docker setup; for real
# production you'd put php-fpm behind nginx or a managed platform instead.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
