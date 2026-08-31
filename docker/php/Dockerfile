FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev icu-dev libzip-dev oniguruma-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql intl zip gd bcmath exif pcntl opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
