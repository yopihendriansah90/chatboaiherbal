FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libsqlite3-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring pcntl pdo_mysql pdo_sqlite xml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
