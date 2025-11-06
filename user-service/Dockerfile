FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    librdkafka-dev \
    autoconf \
    g++ \
    make \
    git \
    unzip \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Kafka extension
RUN pecl install rdkafka && \
    docker-php-ext-enable rdkafka

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy ALL application files first
COPY . .

# Debug: Show what was actually copied
RUN echo "=== Files in /app ===" && ls -la /app/
RUN echo "=== Checking composer.json ===" && cat composer.json | head -20

# Install dependencies with all scripts enabled
RUN composer install --prefer-dist --no-interaction --verbose 2>&1 | tee /tmp/composer-install.log

# Debug: Check vendor directory
RUN echo "=== Vendor directory ===" && ls -la vendor/ | head -20
RUN echo "=== Looking for autoload_runtime.php ===" && \
    find vendor -name "autoload_runtime.php" -type f || echo "FILE NOT FOUND"

# Show what packages were installed
RUN echo "=== Installed packages ===" && composer show | grep runtime || echo "No runtime package found"

# Create necessary directories
RUN mkdir -p var/cache var/log && \
    chmod -R 777 var

# Expose port
EXPOSE 8000

# Run start server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
