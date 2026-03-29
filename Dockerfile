FROM php:7.4-apache

# تثبيت الإضافات اللازمة لقاعدة البيانات
RUN docker-php-ext-install mysqli pdo pdo_mysql

# حل مشكلة الانهيار بتعطيل الموديلات المتعارضة
RUN a2dismod mpm_event || true && a2enmod mpm_prefork || true

# تفعيل الروابط
RUN a2enmod rewrite

# نسخ الملفات
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# المنفذ
EXPOSE 80

CMD ["apache2-foreground"]
