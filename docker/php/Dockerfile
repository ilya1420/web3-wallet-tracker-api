FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    rabbitmq-c-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo pdo_mysql opcache zip \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["php-fpm"]
