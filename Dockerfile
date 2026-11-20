# PHP 8.6 is at RC stage as of 2026-11-19. Switch to php:8.6-cli once the GA image is published.
ARG PHP_IMAGE=php:8.6-rc-cli

FROM composer:2 AS composer

FROM ${PHP_IMAGE}

# gdal-bin and tippecanoe live in this image because the import use case drives them through process
# adapters in the same container. Separate tool containers would be cleaner; see the README.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev gdal-bin tippecanoe \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
