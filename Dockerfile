# استخدام نسخة PHP 7.4 مع Apache المتوافقة مع منظومتك
FROM php:7.4-apache

# تثبيت إضافات MySQL الضرورية
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل خاصية Rewrite لروابط Apache (مهم جداً لـ SaaS)
RUN a2enmod rewrite

# ضبط مسار العمل داخل الحاوية
WORKDIR /var/www/html

# نسخ كافة ملفات المنظومة من المجلد الحالي إلى الحاوية
COPY . /var/www/html/

# ضبط الصلاحيات للمجلد
RUN chown -R www-data:www-data /var/www/html