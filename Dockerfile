FROM php:8.1-apache

# Habilitar mod_rewrite y configurar Apache
RUN a2enmod rewrite
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Instalar extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libxml2-dev \
    unzip \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install soap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copiar archivos del proyecto
COPY . /var/www/html/

# Dar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

WORKDIR /var/www/html

EXPOSE 80

