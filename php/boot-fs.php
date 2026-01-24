<?php

// This file needs to parse without error in PHP < 5.3

if ( 'cli' !== PHP_SAPI ) {
	echo "Only CLI access.\n";
	die( -1 );
}

if ( version_compare( PHP_VERSION, '7.2.24', '<' ) ) {
	printf( "Error: WP-CLI requires PHP %s or newer. You are running version %s.\n", '7.2.24', PHP_VERSION );
	die( -1 );
}

// Check for required extensions to avoid PHP fatal errors.
// Symfony polyfill-mbstring requires ext-iconv, so if neither mbstring nor iconv
// extensions are available, WP-CLI will fail with a fatal error.
if ( ! extension_loaded( 'mbstring' ) && ! extension_loaded( 'iconv' ) ) {
	echo "Error: WP-CLI requires the mbstring or iconv PHP extension to be installed.\n";
	echo "Both extensions are currently missing. Please install at least one of them.\n";
	echo "For more information, see: https://make.wordpress.org/cli/handbook/installing/\n";
	die( -1 );
}

define( 'WP_CLI_ROOT', dirname( __DIR__ ) );

require_once WP_CLI_ROOT . '/php/wp-cli.php';
