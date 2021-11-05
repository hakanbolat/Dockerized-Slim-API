FROM php:7.4-fpm-alpine

MAINTAINER Hakan BOLAT <blt-hkn@hotmail.com>

RUN set -ex \
    && apk --no-cache add libzip-dev curl-dev libpng-dev libxslt-dev icu-dev

RUN pecl channel-update pecl.php.net

RUN set -ex \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS imagemagick-dev libtool \
    && export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS" \
    && pecl install imagick-3.4.4 \
    && docker-php-ext-enable imagick \
    && pecl install xdebug-2.9.6 \
    && docker-php-ext-enable xdebug \
    && apk add --no-cache --virtual .imagick-runtime-deps imagemagick \
    && apk del .phpize-deps

RUN set -ex \
    && apk --no-cache add curl oniguruma oniguruma-dev postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install curl \
    && docker-php-ext-install gd \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install json \
    && docker-php-ext-install xsl \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install opcache

RUN wget https://getcomposer.org/installer -O - -q \
    | php -- --install-dir=/bin --filename=composer --quiet

ADD php.ini /usr/local/etc/php/php.ini


ARG DC_USER_ID
ARG DC_GROUP_ID

RUN apk --no-cache add shadow \
    && usermod -u $DC_USER_ID www-data \
    && groupmod -g $DC_GROUP_ID www-data

USER "$DC_USER_ID:$DC_GROUP_ID"


WORKDIR /web
