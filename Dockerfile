FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY . .

EXPOSE 80
