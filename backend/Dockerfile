FROM php:8.4-cli

# Composer needs unzip and git to download and extract packages.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

# hides the PHP version on every reply
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/zz-hide-php.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
