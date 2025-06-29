FROM php:8.3-fpm

# Установка расширений PHP и системных пакетов
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    librabbitmq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/app

# Копируем исходники
COPY ./app /var/www/html/app

# Установка зависимостей и автоприменение миграций при запуске контейнера
CMD ["/bin/sh", "-c", "composer install --no-interaction && php yii migrate --interactive=0 && php-fpm"] 