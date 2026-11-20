FROM php:8.3-cli

RUN docker-php-ext-install pdo_mysql \
    && printf "upload_max_filesize=2048M\npost_max_size=2048M\nmax_file_uploads=20\nmax_execution_time=600\nmax_input_time=600\n" > /usr/local/etc/php/conf.d/ksers-uploads.ini \
    && mkdir -p /data/abstracts /data/program /data/member_uploads /data/member_videos /data/gallery

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]
