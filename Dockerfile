FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

# Веб-корень приложения — подпапка public/ (туда переехали все отдаваемые файлы).
RUN sed -ri -e 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    Options FollowSymLinks\n    AllowOverride None\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/app-docroot.conf \
    && a2enconf app-docroot

# Лимиты загрузки изображений (галерея, отзывы, фото сайта)
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
