FROM composer:2 AS composer

FROM node:22-alpine AS node

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

RUN docker-php-ext-install -j$(nproc) \
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
RUN apk add --no-cache sqlite-dev && docker-php-ext-install -j$(nproc) pdo_sqlite

# git lets commits run inside the container, where the pre-commit checks live
RUN apk add --no-cache git

# Node runs the frontend checks (ESLint, tsc, Vitest) in the same container as
# the PHP checks. Pinned to node:22-alpine to match the vite service and CI.
# libstdc++ is the one shared lib the node binary links against.
RUN apk add --no-cache libstdc++
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Fire the pre-commit checks for any commit made inside the container, not only
# via bin/commit. The host's git config is separate, so host commits are
# unaffected and intentionally skip the checks, since the host has no toolchain.
RUN git config --system core.hooksPath /var/www/html/.githooks

RUN echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini

RUN printf 'upload_max_filesize = 25M\npost_max_size = 30M\n' > /usr/local/etc/php/conf.d/uploads.ini

RUN printf 'memory_limit = 512M\n' > /usr/local/etc/php/conf.d/memory.ini

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
