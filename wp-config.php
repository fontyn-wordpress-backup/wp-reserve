<?php
// First Refresh test
define('DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress_db');
define('DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wpuser');
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'password');
define('DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'mysqlserver.mysql.database.azure.com');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('EXT_DB_NAME', getenv('EXT_DB_NAME') ?: 'external_db');
define('EXT_DB_USER', getenv('EXT_DB_USER') ?: 'wpuser');
define('EXT_DB_PASSWORD', getenv('EXT_DB_PASSWORD') ?: 'password');
define('EXT_DB_HOST', DB_HOST); 

define('WP_HOME','https://fonteyn.cloud');
define('WP_SITEURL','https://fonteyn.cloud');

define('AUTH_KEY', getenv('AUTH_KEY'));
define('SECURE_AUTH_KEY', getenv('SECURE_AUTH_KEY'));
define('LOGGED_IN_KEY', getenv('LOGGED_IN_KEY'));
define('NONCE_KEY', getenv('NONCE_KEY'));
define('AUTH_SALT', getenv('AUTH_SALT'));
define('SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT'));
define('LOGGED_IN_SALT', getenv('LOGGED_IN_SALT'));
define('NONCE_SALT', getenv('NONCE_SALT'));

define('CBP_ENCRYPTION_KEY', getenv('CBP_ENCRYPTION_KEY'));
define('CBP_ENCRYPTION_IV', getenv('CBP_ENCRYPTION_IV'));

define('FS_METHOD', 'direct');

define('WP_CACHE', true);
define('WP_POST_REVISIONS', 5);



$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

// Set up WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
