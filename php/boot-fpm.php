<?php

namespace WPCLI\FPM;

// This file needs to parse without error in PHP < 5.3

if ( 'fpm-fcgi' !== PHP_SAPI ) {
	echo "Only FPM access.\n";
	die( -1 );
}

if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
	printf( "Error: WP-CLI requires PHP %s or newer. You are running version %s.\n", '5.6.0', PHP_VERSION );
	die( -1 );
}

define( 'WP_CLI_ROOT', dirname( __DIR__ ) );

// phpcs:ignore
$GLOBALS['argv'] = $_POST;
// phpcs:ignore
$_SERVER['argv'] = $_POST;

define( 'STDIN', fopen( 'php://input', 'r' ) );
define( 'STDOUT', fopen( 'php://output', 'w' ) );
define( 'STDERR', fopen( 'php://output', 'w' ) );

require_once WP_CLI_ROOT . '/php/wp-cli.php';

