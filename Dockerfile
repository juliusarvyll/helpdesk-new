FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip zip curl libzip-dev libicu-dev

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app