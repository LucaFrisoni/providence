# Imagen de Providence para el entorno de desarrollo de la extensión.
# Requisitos según https://docs.collectiveaccess.org/providence/user/setup/systemReq
# y el README de esta versión (PHP 8.2/8.3).
#
# El código de Providence se copia dentro de la imagen (rápido y sin modificar su núcleo).
# Solo app/plugins se monta desde el repositorio para editarlo en vivo (ver compose.yaml).
# El contexto de build es la raíz del repositorio; .dockerignore excluye secretos y datos locales.
FROM php:8.3-apache-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Configuración global de composer (avisos de seguridad aceptados, ver composer-config.json)
ENV COMPOSER_HOME=/usr/local/composer
COPY docker/providence/composer-config.json /usr/local/composer/config.json

# Extensiones PHP requeridas (curl, xml y mbstring ya vienen en la imagen base)
RUN install-php-extensions gd mysqli pdo_mysql zip intl bcmath gmp opcache pcntl exif

# Herramientas externas: composer (git, unzip), procesamiento de imágenes y metadatos
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip default-mysql-client \
        graphicsmagick ghostscript poppler-utils mediainfo libimage-exiftool-perl \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Dependencias de composer: primero solo composer.json/lock para aprovechar la caché de Docker
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# Código de Providence y setup.php del entorno Docker (lee las credenciales de variables de entorno)
COPY . .
COPY docker/providence/setup.php setup.php
# Carpetas que Apache debe poder escribir (según app/lib/ConfigurationCheck.php de Providence)
RUN composer dump-autoload --no-interaction \
    && mkdir -p app/fonts uploads \
    && chown -R www-data:www-data app/tmp app/log app/fonts media uploads \
       vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache

COPY docker/providence/php.ini /usr/local/etc/php/conf.d/zz-providence.ini
COPY docker/providence/configure-admin.php /usr/local/lib/providence/configure-admin.php
COPY docker/providence/entrypoint.sh /usr/local/bin/providence-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/providence-entrypoint && chmod +x /usr/local/bin/providence-entrypoint

ENTRYPOINT ["providence-entrypoint"]
CMD ["apache2-foreground"]
