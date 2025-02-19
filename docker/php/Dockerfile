FROM php:8.2-fpm
ARG UID

RUN apt update
RUN apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    sudo \
    bash-completion \
    mariadb-client    
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql
RUN pecl install xdebug; "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN adduser --shell $(which bash) --disabled-pasdsword --gecos "" --uid ${UID} container && \
    usermod -aG sudo container
RUN echo "container ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
RUN sudo -u container echo "source /etc/profile.d/bash_completion.sh" >> /home/container/.bashrc
RUN mkdir /var/lib/php /var/lib/php/volume
RUN chown container:container /var/lib/php/volume; chmod 1733 /var/lib/php/volume
COPY docker/php/php.ini /usr/local/etc/php/php.ini

USER container

WORKDIR /var/www/html
