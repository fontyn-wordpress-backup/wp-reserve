#!/bin/bash
set -e

if [ ! -d "/var/www/html/wp-content/plugins/custom-plugin" ]; then
    echo "Custom plugin not found in plugins folder. Copying from /tmp/custom-plugin..."
    cp -r /tmp/custom-plugin /var/www/html/wp-content/plugins/
    chown -R www-data:www-data /var/www/html/wp-content/plugins/custom-plugin
else
    echo "Custom plugin already exists in plugins folder."
fi

exec "$@"
