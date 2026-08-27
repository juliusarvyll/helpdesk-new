FROM composer:2 AS vendor
WORKDIR /app
RUN install-php-extensions intl
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && composer run-script post-autoload-dump --no-interaction

FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    gd \
    intl \
    zip \
    exif \
    pcntl \
    opcache

WORKDIR /app

COPY Caddyfile /etc/frankenphp/Caddyfile
COPY --from=vendor /app /app

RUN php artisan filament:assets \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && SERVER_NAME=\":$PORT\" exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile"]
