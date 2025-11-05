FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    librdkafka-dev \
    autoconf \
    g++ \
    make \
    git \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Kafka extension
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy application files
COPY . .

# Generate autoload files
RUN composer dump-autoload --optimize

# Create necessary directories
RUN mkdir -p var/cache var/log && \
    chmod -R 777 var

# Expose port
EXPOSE 8000

# Run start server
CMD php -S 0.0.0.0:8000 -t public