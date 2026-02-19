FROM php:8.2-apache

# SQLite PDO
RUN apt-get update \
  && apt-get install -y --no-install-recommends libzip-dev zip \
  && rm -rf /var/lib/apt/lists/*

COPY src/ /var/www/html/
# include optional php.ini (if present in repo)
COPY php.ini /usr/local/etc/php/conf.d/php-app.ini
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80
