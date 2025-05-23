FROM wordpress:6.8-php8.2-apache

# Accept the salts as build arguments
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

COPY custom-plugin /var/www/html/wp-content/plugins/reservation-plugin

COPY ./docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
