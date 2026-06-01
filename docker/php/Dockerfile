FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    rabbitmq-c-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache zip \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && apk del $PHPIZE_DEPS

RUN addgroup -g 1000 symfony && adduser -u 1000 -G symfony -s /bin/bash -D symfony

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

USER symfony

CMD ["php-fpm"]
