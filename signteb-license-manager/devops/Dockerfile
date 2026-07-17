# SignTeb License Manager — production image (PHP-FPM 8.3)
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-dev libzip-dev zip oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl zip bcmath opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/slm

# --- dependencies layer (cached) ---
FROM base AS vendor
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# --- application ---
FROM base AS app
COPY --from=vendor /var/www/slm/vendor ./vendor
COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY devops/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
