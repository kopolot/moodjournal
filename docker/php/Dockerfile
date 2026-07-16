FROM php:8.5-fpm
ARG UID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        librabbitmq-dev \
        postgresql-client \
        sudo \
        unzip \
        zip \
    && docker-php-ext-install bcmath intl pdo_pgsql pgsql \
    && pecl install amqp xdebug \
    && docker-php-ext-enable amqp xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN adduser --shell /bin/bash --disabled-password --gecos "" --uid "${UID}" container \
    && usermod -aG sudo container \
    && echo "container ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers \
    && mkdir -p /var/lib/php/volume \
    && chown container:container /var/lib/php/volume \
    && chmod 1733 /var/lib/php/volume

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-local.ini

USER container
WORKDIR /var/www/html
