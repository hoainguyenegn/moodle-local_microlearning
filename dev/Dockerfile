FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j4 mysqli intl zip gd soap exif opcache && rm -rf /var/lib/apt/lists/*
RUN printf 'max_input_vars=5000\nmemory_limit=512M\nupload_max_filesize=32M\npost_max_size=40M\n' > /usr/local/etc/php/conf.d/moodle.ini
