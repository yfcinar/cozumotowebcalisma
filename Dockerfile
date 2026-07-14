# Çözüm Oto Elektrik — production/test container
# Render, Railway, Fly.io gibi Docker destekleyen platformlarda çalışır.

FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite

WORKDIR /var/www/html
COPY . .
COPY --from=deps /app/vendor ./vendor

# Web kökü public/ olacak şekilde Apache'yi ayarla
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/app.conf \
    && a2enconf app \
    && mkdir -p var/cache/twig \
    && chown -R www-data:www-data /var/www/html/database /var/www/html/public/assets/uploads /var/www/html/var

# Varsayılan ortam: SQLite ile hemen çalışır (test/demo için)
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_DRIVER=sqlite \
    DB_SQLITE_PATH=database/cozumoto.sqlite

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
