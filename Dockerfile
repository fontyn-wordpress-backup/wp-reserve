FROM wordpress:6.8-php8.2-apache

ARG AUTH_KEY
ARG SECURE_AUTH_KEY
ARG LOGGED_IN_KEY
ARG NONCE_KEY
ARG AUTH_SALT
ARG SECURE_AUTH_SALT
ARG LOGGED_IN_SALT
ARG NONCE_SALT

ENV AUTH_KEY=$AUTH_KEY
ENV SECURE_AUTH_KEY=$SECURE_AUTH_KEY
ENV LOGGED_IN_KEY=$LOGGED_IN_KEY
ENV NONCE_KEY=$NONCE_KEY
ENV AUTH_SALT=$AUTH_SALT
ENV SECURE_AUTH_SALT=$SECURE_AUTH_SALT
ENV LOGGED_IN_SALT=$LOGGED_IN_SALT
ENV NONCE_SALT=$NONCE_SALT

COPY custom-plugin /var/www/html/wp-content/plugins/
COPY wp-config.php /var/www/html/wp-config.php

RUN chown -R www-data:www-data /var/www/html/wp-content/plugins/ /var/www/html/wp-config.php

RUN a2enmod ssl

RUN echo '<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    SSLEngine on
    SSLCertificateFile /certs/wordpress.crt
    SSLCertificateKeyFile /certs/wordpress.key

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>' > /etc/apache2/sites-available/default-ssl.conf

RUN a2ensite default-ssl.conf

EXPOSE 443

CMD ["apache2-foreground"]
