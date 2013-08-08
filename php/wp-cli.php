<?php

// Can be used by plugins/themes to check if wp-cli is running or not
define( 'WP_CLI', true );

define( 'WP_CLI_VERSION', '0.12-alpha' );

include WP_CLI_ROOT . '/php/utils.php';
include WP_CLI_ROOT . '/php/dispatcher.php';
include WP_CLI_ROOT . '/php/class-wp-cli.php';
include WP_CLI_ROOT . '/php/class-wp-cli-command.php';

\WP_CLI\Utils\load_dependencies();

WP_CLI::init();

WP_CLI::get_runner()->before_wp_load();

// Load wp-config.php code, in the global scope
eval( WP_CLI::get_runner()->get_wp_config_code() );

// Simulate a /wp-admin/ page load
$_SERVER['PHP_SELF'] = '/wp-admin/index.php';
define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', false );
define( 'WP_USER_ADMIN', false );

// Load Core, mu-plugins, plugins, themes etc.
require WP_CLI_ROOT . '/php/wp-settings-cli.php';

// Fix memory limit. See http://core.trac.wordpress.org/ticket/14889
@ini_set( 'memory_limit', -1 );

require ABSPATH . 'wp-admin/includes/admin.php';
do_action( 'admin_init' );

WP_CLI::get_runner()->after_wp_load();

