FROM php:7.4-apache

# تثبيت مكتبات MySQL الضرورية للمنظومة
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل خاصية الروابط فقط
RUN a2enmod rewrite

# ضبط اسم السيرفر
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# المنفذ الافتراضي
EXPOSE 80

CMD ["apache2-foreground"]
