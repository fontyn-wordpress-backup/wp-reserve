#!/bin/bash
chown -R www-data:www-data /var/www/html/wp-content
exec apache2-foreground
