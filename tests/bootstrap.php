<?php

if ( ! defined( 'WP_CLI_ROOT' ) ) {
	define( 'WP_CLI_ROOT', dirname( __DIR__ ) );
}

if ( file_exists( WP_CLI_ROOT . '/vendor/autoload.php' ) ) {
	define( 'WP_CLI_VENDOR_DIR', WP_CLI_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php' ) ) {
	define( 'WP_CLI_VENDOR_DIR', dirname( dirname( WP_CLI_ROOT ) ) );
}

require_once WP_CLI_VENDOR_DIR . '/autoload.php';
require_once WP_CLI_ROOT . '/php/utils.php';
require_once WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php';

require_once __DIR__ . '/includes/wpdb.php';

// Load WP-CLI Tests TestCase class
if ( file_exists( WP_CLI_VENDOR_DIR . '/wp-cli/wp-cli-tests/tests/includes/TestCase.php' ) ) {
	require_once WP_CLI_VENDOR_DIR . '/wp-cli/wp-cli-tests/tests/includes/TestCase.php';
}

\WpOrg\Requests\Autoload::register();
