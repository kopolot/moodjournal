FROM php:8.5-fpm
ARG UID

RUN apt update
RUN apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    sudo \
    bash-completion \
    mariadb-client \
    libicu-dev \
    nano \
    postgresql-common \
    postgresql-client \
    librabbitmq-dev \ 
    cron
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    bcmath \
    intl
RUN pecl install xdebug
RUN pecl install amqp
RUN docker-php-ext-enable xdebug amqp
RUN adduser --shell $(which bash) --disabled-password --gecos "" --uid ${UID} container
RUN usermod -aG sudo container
RUN echo "container ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
# RUN sudo -u container echo "source /etc/profile.d/bash_completion.sh" >> /home/container/.bashrc
RUN echo alias sudo="sudo -E" > /etc/profile.d/00sudo.sh
# to jest chujowo
RUN mkdir /var/lib/php /var/lib/php/volume
RUN chown container:container /var/lib/php/volume; chmod 1733 /var/lib/php/volume
COPY docker/php/php.ini /usr/local/etc/php/php.ini

USER container
RUN echo 'alias sudo="sudo -E"' >> /home/container/.bashrc

WORKDIR /var/www/html