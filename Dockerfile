FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/samtli-entrypoint

RUN chmod +x /usr/local/bin/samtli-entrypoint

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY . .

EXPOSE 80

ENTRYPOINT ["samtli-entrypoint"]
CMD ["apache2-foreground"]
