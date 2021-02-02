ARG PHP_VERSION
FROM php:${PHP_VERSION}-fpm-alpine AS base
ENV PHP_VERSION "$PHP_VERSION"

ENV LANG=en_US.UTF-8 \
    LANGUAGE=en_US.UTF-8 \
    LC_ALL=en_US.UTF-8 \
    PHP_INI_TIMEZONE=Europe/Moscow \
    PHP_INI_MEMORY_LIMIT=512M \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_HOME=/opt/composer \
    PHP_EXTENTIONS="dom iconv posix soap intl bcmath sockets zip gd pdo_mysql curl mysqli pcntl pdo json simplexml session xml ctype pcntl" \
    PECL_EXTENTIONS="apcu timezonedb uuid xdebug imagick" \
    PHP_BUILDDEPS="openssl-dev icu-dev libmcrypt-dev pcre-dev libzip-dev curl-dev libxml2-dev gcc g++ libtool make binutils mysql-dev imagemagick-dev libmemcached-dev cyrus-sasl-dev" \
    PHP_RUNDEPS="util-linux-dev pcre-dev zlib-dev libzip-dev libgd libpng-dev libpq icu-libs libmcrypt autoconf libmemcached-libs imagemagick" \
    COMMON_PACKAGES="git bash bash-completion nano curl coreutils fcgi unzip tzdata net-tools openssh-client openssh-keygen openssh-keysign libmemcached ca-certificates openrc sudo"

# set TimeZone
RUN apk add --no-cache ${COMMON_PACKAGES} \
    && ln -fs /usr/share/zoneinfo/${PHP_INI_TIMEZONE} /etc/localtime \
    && echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers


# install PHP extentions
RUN apk add --no-cache ${PHP_RUNDEPS} \
    && apk add --no-cache --virtual .php_builddeps ${PHP_BUILDDEPS} ${PHPIZE_DEPS} \
    && docker-php-ext-install ${PHP_EXTENTIONS} \
    && docker-php-ext-enable ${PHP_EXTENTIONS} \
    # install PECL extentions
    && for EXT in ${PECL_EXTENTIONS}; do \
        pecl install ${EXT} \
        && EXT=$(echo ${EXT} | cut -f1 -d-) \
        && docker-php-ext-enable ${EXT} && \
        ( php -m | grep "^${EXT}$" ); \
    done \
    && apk del .php_builddeps


# install composer
COPY --from=composer:1.9.0 /usr/bin/composer /usr/bin/composer


# create user
ARG USER_GID=1000
ARG USER_UID=1000
RUN addgroup -g ${USER_GID} app \
  && adduser -D -s /bin/bash -G app -u ${USER_UID} app \
  && composer global require bamarni/symfony-console-autocomplete \
  && symfony-autocomplete composer | tee /usr/share/bash-completion/completions/composer \
  && chown -R app:app /opt \
  && echo "alias ll='ls -lah'" > /etc/profile.d/aliases.sh \
  && echo "alias ..='cd ..'" >> /etc/profile.d/aliases.sh


ENV PHP_VERSION=7.4
RUN rm -rf /usr/local/etc/php/php-fpm.d/*.conf \
  && echo "PHP_VERSION: ${PHP_VERSION}"

COPY php-${PHP_VERSION}.ini         /usr/local/etc/php/php.ini
COPY conf.d/                        /usr/local/etc/php/conf.d
COPY php-fpm-${PHP_VERSION}.conf    /usr/local/etc/php-fpm.conf
COPY php-fpm.d/                     /usr/local/etc/php-fpm.d/

RUN composer global require hirak/prestissimo

EXPOSE 9000
WORKDIR /srv
RUN chown -R app:app /srv
USER app



