# PHP FPM on Alpine, pinned to exact versions so rebuilds are reproducible
FROM php:8.5.12-fpm-alpine3.24

# Environment variables
ARG user
ARG uid
# Required by pnpm latest on this Node build: enables the built-in node:sqlite module.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_OPTIONS=--experimental-sqlite

# Create user and group
RUN addgroup -g $uid $user && \
    adduser -G $user -u $uid -D $user

# Install system dependencies and Node.js (current Alpine channel)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    freetype-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    jpeg-dev \
    bash \
    autoconf \
    build-base \
    mariadb-dev \
    mariadb-client \
    postgresql-dev \
    nodejs-current \
    npm \
    && npm install -g pnpm@12.15.0

# Install required PHP extensions for Laravel with MariaDB and PostgreSQL
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl zip

# Install Redis and coverage extensions
RUN pecl install redis-6.3.0 pcov-1.0.12 && docker-php-ext-enable redis pcov

# Install pinned Composer
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

# Load PHP runtime security defaults
COPY .docker/php/config/security.ini /usr/local/etc/php/conf.d/security.ini

# Load PHP coverage defaults for PHPUnit
COPY .docker/php/config/coverage.ini /usr/local/etc/php/conf.d/coverage.ini

# Switch to non-root user
USER $user

# Set Laravel working directory
WORKDIR /var/www

# Default command
CMD ["php-fpm"]
