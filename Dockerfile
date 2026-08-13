<<<<<<< HEAD
FROM php:8.4-fpm

COPY sophos-ca.crt /usr/local/share/ca-certificates/sophos-ca.crt
RUN update-ca-certificates
=======
FROM php:8.4-cli

RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list
>>>>>>> bfaad72ade72f4060e251440c333ed98a8c55d91

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --optimize-autoloader

<<<<<<< HEAD
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
=======
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
>>>>>>> bfaad72ade72f4060e251440c333ed98a8c55d91
