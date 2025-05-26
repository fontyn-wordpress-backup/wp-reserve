#!/bin/bash
set -e

if [ ! -f /var/www/html/wp-config.php ]; then
    echo "Copying custom wp-config.php..."
    cp wp-config.php /var/www/html/wp-config.php
fi

exec "$@"
