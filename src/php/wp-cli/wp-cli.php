<?php

// Can be used by plugins/themes to check if wp-cli is running or not
define( 'WP_CLI', true );

define( 'WP_CLI_VERSION', '0.7.0-beta' );

define( 'WP_CLI_ROOT', __DIR__ . '/' );

include WP_CLI_ROOT . 'utils.php';
include WP_CLI_ROOT . 'dispatcher.php';
include WP_CLI_ROOT . 'class-wp-cli.php';
include WP_CLI_ROOT . 'class-wp-cli-command.php';
include WP_CLI_ROOT . 'class-wp-cli-command-with-meta.php';
include WP_CLI_ROOT . 'class-wp-cli-command-with-upgrade.php';
include WP_CLI_ROOT . 'man.php';

include WP_CLI_ROOT . '../php-cli-tools/lib/cli/cli.php';
\cli\register_autoload();

WP_CLI::before_wp_load();

// Load WordPress, in the global scope
require WP_ROOT . 'wp-load.php';

// Fix memory limit. See http://core.trac.wordpress.org/ticket/14889
@ini_set( 'memory_limit', -1 );

// Load all admin utilities
require ABSPATH . 'wp-admin/includes/admin.php';

WP_CLI::after_wp_load();

