#-----------------------------------------------
# Stage 1: Builder
#-----------------------------------------------
FROM php:8.2-fpm-alpine AS builder

RUN apk add --no-cache \
    postgresql-dev \
    librdkafka-dev \
    autoconf \
    g++ \
    make \
    git \
    unzip \
    linux-headers

RUN docker-php-ext-install pdo pdo_pgsql

RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install --prefer-dist --no-dev --no-interaction --optimize-autoloader --no-scripts --no-progress

COPY . .

RUN composer run-script --no-dev post-install-cmd || true && \
    composer dump-autoload --optimize --classmap-authoritative

#-----------------------------------------------
# Stage 2: Final Image
#-----------------------------------------------
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    libpq \
    postgresql-libs \
    librdkafka \
    nginx \
    curl

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN echo 'listen = 127.0.0.1:9000' >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

COPY --from=builder /app /app

COPY nginx.conf /etc/nginx/http.d/default.conf

RUN mkdir -p var/cache var/log /var/log/nginx /run/nginx \
    /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi && \
    chown -R www-data:www-data /app /var/log/nginx /run/nginx \
    /var/lib/nginx && \
    chmod -R 755 var /var/log/nginx /run/nginx /var/lib/nginx

EXPOSE 8000

USER www-data

CMD ["sh", "-c", "php-fpm -D && exec nginx -g 'daemon off;'"]
