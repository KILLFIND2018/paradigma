FROM php:8.2-fpm

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. Сначала копируем composer файлы
COPY composer.json composer.lock ./


# 2. Устанавливаем зависимости (кешируем этот слой)
RUN composer install --no-scripts --no-autoloader

# 3. Копируем весь остальной код (кроме vendor)
COPY . .

# 4. Генерируем autoload
RUN composer dump-autoload --optimize

# 5. Настраиваем права на папки
RUN mkdir -p /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    && chown -R www-data:www-data /var/www/storage \
    && chown -R www-data:www-data /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# 6. Создаем .env если нет
RUN if [ ! -f .env ]; then cp .env.example .env && php artisan key:generate; fi

# 7. Запускаем сервер
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
