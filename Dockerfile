FROM php:8.2-apache

RUN docker-php-ext-install mysqli

COPY index.php /var/www/html/index.php
COPY telegram_bot.php /var/www/html/telegram_bot.php

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf && \
    sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]
