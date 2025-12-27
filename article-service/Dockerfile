#---------------------
# Base - Image commune
#---------------------
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    linux-headers \
    make \
    postgresql-dev \
    librdkafka-dev

RUN apk add --no-cache \
    libpq \
    postgresql-libs \
    librdkafka \
    nginx \
    curl \
    git \
    unzip \
    dos2unix

RUN docker-php-ext-install pdo pdo_pgsql && \
    pecl install rdkafka && \
    docker-php-ext-enable rdkafka

RUN apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN echo 'listen = 127.0.0.1:9000' >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN dos2unix /usr/local/bin/docker-entrypoint

RUN mkdir -p var/cache var/log /var/log/nginx /run/nginx \
    /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi && \
    chmod -R 755 /var/log/nginx /run/nginx /var/lib/nginx

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]

CMD ["sh", "-c", "php-fpm -D && exec nginx -g 'daemon off;'"]

#-----------------------------------------------
# Dev - Code et vendor montés depuis l'host
#-----------------------------------------------
FROM base AS dev

ENV APP_ENV=dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN mkdir -p /app/var && chown -R 1000:1000 /app /app/var /var/log/nginx /run/nginx /var/lib/nginx

USER 1000:1000

#-----------------------------------------------
# Prod - Vendor installé
#-----------------------------------------------
FROM base AS prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./

RUN composer install --prefer-dist --no-dev --no-interaction --optimize-autoloader --no-scripts --no-progress

COPY . .

RUN composer run-script --no-dev post-install-cmd || true && \
    composer dump-autoload --optimize --classmap-authoritative

RUN chown -R www-data:www-data /app /var/log/nginx /run/nginx /var/lib/nginx && \
    chmod -R 755 /app/var

USER www-data
