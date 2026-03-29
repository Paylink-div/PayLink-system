FROM php:7.4-apache

# تثبيت الإضافات اللازمة
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تعطيل الموديل المسبب للمشكلة بشكل نهائي وتفعيل البديل الصحيح
RUN a2dismod mpm_event || true && a2enmod mpm_prefork

# تفعيل خاصية الروابط
RUN a2enmod rewrite

# ضبط اسم السيرفر لتفادي التحذيرات
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# نسخ ملفات المنظومة
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# تحديد المنفذ الافتراضي لـ Railway
ENV PORT 80
EXPOSE 80

# تشغيل السيرفر
CMD ["apache2-foreground"]
