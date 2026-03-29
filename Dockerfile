FROM richarvey/nginx-php-fpm:1.10.3

# نسخ ملفات المنظومة
COPY . /var/www/html

# إعدادات إضافية بسيطة
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html
ENV PHP_ERRORS_STDERR 1

EXPOSE 80
