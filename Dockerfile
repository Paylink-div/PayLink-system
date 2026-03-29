# استخدام نسخة مستقرة من PHP 7.4 مع Apache
FROM php:7.4-apache

# 1. تثبيت إضافات قاعدة البيانات الضرورية
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 2. حل مشكلة الانهيار (تعطيل الموديلات المتعارضة وتفعيل المستقر)
# هذا الجزء هو المسؤول عن حل رسالة "More than one MPM loaded"
RUN a2dismod mpm_event || true && \
    a2dismod mpm_worker || true && \
    a2enmod mpm_prefork || true

# 3. تفعيل خاصية الروابط (Rewrite)
RUN a2enmod rewrite

# 4. ضبط إعدادات السيرفر الأساسية
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 5. تجهيز ملفات المنظومة
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# 6. تحديد المنفذ (Railway يستخدم منفذ 80 افتراضياً)
EXPOSE 80

# تشغيل السيرفر
CMD ["apache2-foreground"]
