FROM composer:2 AS composer

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    pcre-dev \
    oniguruma-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    bcmath \
    opcache \
    zip \
    intl \
    gd \
    exif \
    pcntl

# pdo_sqlite backs the in-memory test database (mirrors the CI runner)
RUN apk add --no-cache sqlite-dev && docker-php-ext-install pdo_sqlite

# git lets commits run inside the container, where the pre-commit checks live
RUN apk add --no-cache git

RUN echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini

RUN addgroup -g 1000 laravel && \
    adduser -D -u 1000 -G laravel laravel

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --chown=laravel:laravel . .

RUN if [ "$BUILD_ENV" = "production" ]; then \
    composer install --no-dev --optimize-autoloader; \
  fi

USER laravel

EXPOSE 9000

CMD ["php-fpm"]
