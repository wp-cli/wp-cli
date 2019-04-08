Feature: wp-config
  Scenario: Default WordPress install with wp-config.php
    Given a WP installation
    And a wp-config.php file:
      """
<?php
// ** MySQL settings ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wp_cli_test');

/** MySQL database username */
define('DB_USER', 'wp_cli_test');

/** MySQL database password */
define('DB_PASSWORD', 'password1');

/** MySQL hostname */
define('DB_HOST', '127.0.0.1');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

$table_prefix = 'wp_';

define( 'WP_ALLOW_MULTISITE', true );
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
  define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
      """
      When I try `wp --debug`
      Then STDERR should contain:
      """
      wp-config.php path: {RUN_DIR}/wp-config.php
      """

  Scenario: Default WordPress install with WP_CONFIG_PATH specified in environment variable
    Given a WP installation
    And a wp-config-override.php file:
      """
<?php
// ** MySQL settings ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wp_cli_test');

/** MySQL database username */
define('DB_USER', 'wp_cli_test');

/** MySQL database password */
define('DB_PASSWORD', 'password1');

/** MySQL hostname */
define('DB_HOST', '127.0.0.1');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

$table_prefix = 'wp_';

define( 'WP_ALLOW_MULTISITE', true );
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
  define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
      """
      When I try `WP_CONFIG_PATH=wp-config-override.php wp --debug`
      Then STDERR should contain:
      """
      wp-config.php path: {RUN_DIR}/wp-config-override.php
      """"