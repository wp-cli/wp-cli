<?php

// This file needs to parse without error in PHP < 5.3

if ( 'cli' !== PHP_SAPI ) {
	echo "Only CLI access.\n";
	die( -1 );
}

if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
	printf( "Error: WP-CLI requires PHP %s or newer. You are running version %s.\n", '5.6.0', PHP_VERSION );
	die( -1 );
}

define( 'WP_CLI_ROOT', dirname( __DIR__ ) );

require_once WP_CLI_ROOT . '/php/wp-cli.php';
