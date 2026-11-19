# PHP 8.6 is at RC stage as of 2026-11-19. Switch to php:8.6-cli once the GA image is published.
ARG PHP_IMAGE=php:8.6-rc-cli

FROM composer:2 AS composer

FROM ${PHP_IMAGE}

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
