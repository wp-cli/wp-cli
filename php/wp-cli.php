<?php

// Can be used by plugins/themes to check if WP-CLI is running or not.
define( 'WP_CLI', true );
define( 'WP_CLI_VERSION', trim( file_get_contents( WP_CLI_ROOT . '/VERSION' ) ) );
define( 'WP_CLI_START_MICROTIME', microtime( true ) );

if ( file_exists( WP_CLI_ROOT . '/vendor/autoload.php' ) ) {
	define( 'WP_CLI_VENDOR_DIR', WP_CLI_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php' ) ) {
	define( 'WP_CLI_VENDOR_DIR', dirname( dirname( WP_CLI_ROOT ) ) );
} elseif ( file_exists( dirname( WP_CLI_ROOT ) . '/vendor/autoload.php' ) ) {
	define( 'WP_CLI_VENDOR_DIR', dirname( WP_CLI_ROOT ) . '/vendor' );
} else {
	define( 'WP_CLI_VENDOR_DIR', WP_CLI_ROOT . '/vendor' );
}

require_once WP_CLI_ROOT . '/php/compat.php';

// Set common headers, to prevent warnings from plugins.
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
$_SERVER['HTTP_USER_AGENT'] = '';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

require_once WP_CLI_ROOT . '/php/bootstrap.php';

if ( getenv( 'WP_CLI_EARLY_REQUIRE' ) ) {
	require_once getenv( 'WP_CLI_EARLY_REQUIRE' );
}

WP_CLI\bootstrap();
