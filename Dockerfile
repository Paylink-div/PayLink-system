FROM php:7.4-apache

# تثبيت الإضافات الضرورية
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تعطيل mpm_event وتفعيل mpm_prefork لحل مشكلة الـ Logs
RUN a2dismod mpm_event && a2enmod mpm_prefork

# تفعيل خاصية الروابط (Rewrite)
RUN a2enmod rewrite

# إعداد السيرفر
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# تحديد المنفذ
EXPOSE 80

# أمر التشغيل لضمان عدم حدوث تعارض
CMD ["apache2-foreground"]
