# =============================================================================
# proj-base — Multi-stage Dockerfile
# Laravel 13 + PHP 8.3-FPM + Node 22 (Vite 8 / Tailwind CSS 4)
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: base — PHP runtime with all required extensions
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS base

LABEL maintainer="proj-base"

# Build-time toggles
ARG INSTALL_XDEBUG=false
ARG UID=1000
ARG GID=1000

# System dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    postgresql-dev \
    supervisor \
    dcron \
    && rm -rf /var/cache/apk/*

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        mysqli \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
        pcntl \
        mbstring \
        exif

# Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Xdebug (conditional)
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
        apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS \
        && pecl install xdebug \
        && docker-php-ext-enable xdebug \
        && apk del .xdebug-deps; \
    fi

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match host user UID/GID to avoid permission issues on bind mounts
RUN deluser --remove-home www-data 2>/dev/null || true \
    && addgroup -g ${GID} -S www-data \
    && adduser -u ${UID} -S -G www-data -s /bin/sh www-data

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Stage 2: composer — install PHP dependencies (layer-cached)
# ---------------------------------------------------------------------------
FROM base AS composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Stage 3: node — build frontend assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS node

WORKDIR /var/www/html

# Copy dependency manifests first for caching
COPY package.json package-lock.json* .npmrc ./
RUN npm ci --ignore-scripts

# Copy source for build
COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 4: app — final production image
# ---------------------------------------------------------------------------
FROM base AS app

# Custom PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

# Application code + vendor
COPY --from=composer /var/www/html/vendor /var/www/html/vendor
COPY . .

# Built frontend assets
COPY --from=node /var/www/html/public/build /var/www/html/public/build

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Storage & cache directory permissions
RUN mkdir -p \
        storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        public

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
