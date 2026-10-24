FROM php:8.2-apache

# Cài đặt các thư viện phụ thuộc cho xử lý ảnh (GD, WebP, JPEG, PNG)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Bật Apache rewrite và headers module
RUN a2enmod rewrite headers

# Cấu hình bảo mật và giới hạn upload PHP (Hardening)
RUN { \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 12M'; \
    echo 'memory_limit = 256M'; \
    echo 'expose_php = Off'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/apache2/php_error.log'; \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.use_strict_mode = 1'; \
} > /usr/local/etc/php/conf.d/gallery-custom.ini

# Tạo thư mục lưu trữ ảnh và phân quyền www-data an toàn (755, không dùng 777)
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod 755 /var/www/html/uploads

WORKDIR /var/www/html

# Sao chép mã nguồn ứng dụng
COPY src/ /var/www/html/

# Phân quyền cho www-data
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
