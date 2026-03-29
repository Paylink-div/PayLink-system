# استخدام نسخة PHP 7.4 مع Apache المتوافقة مع منظومتك
FROM php:7.4-apache

# حل مشكلة تعارض MPM في السيرفر (المشكلة التي ظهرت في الـ Logs)
RUN a2dismod mpm_event && a2enmod mpm_prefork

# تثبيت إضافات MySQL الضرورية
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل خاصية Rewrite لروابط Apache (مهم جداً لـ SaaS)
RUN a2enmod rewrite

# ضبط مسار العمل داخل الحاوية
WORKDIR /var/www/html

# نسخ كافة ملفات المنظومة من المجلد الحالي إلى الحاوية
COPY . /var/www/html/

# ضبط الصلاحيات للمجلد لضمان عمل الصور والملفات بشكل صحيح
RUN chown -R www-data:www-data /var/www/html

# التأكد من أن السيرفر يعمل على المنفذ 80
EXPOSE 80
