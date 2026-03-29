# استخدام نسخة PHP 7.4 مع Apache
FROM php:7.4-apache

# 1. تثبيت إضافات MySQL الضرورية للمنظومة
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 2. حل مشكلة الـ MPM (تعطيل الموديل المسبب للانهيار وتفعيل المستقر)
RUN a2dismod mpm_event || true && a2enmod mpm_prefork || true

# 3. تفعيل خاصية Rewrite لروابط Apache
RUN a2enmod rewrite

# 4. ضبط اسم السيرفر لتفادي رسائل التحذير
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 5. ضبط مسار العمل ونسخ ملفات المنظومة
WORKDIR /var/www/html
COPY . /var/www/html/

# 6. ضبط الصلاحيات للمجلد
RUN chown -R www-data:www-data /var/www/html

# 7. تحديد المنفذ الافتراضي
EXPOSE 80

# تشغيل السيرفر في المقدمة
CMD ["apache2-foreground"]
