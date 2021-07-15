<?php

namespace WP_CLI\FCGI;

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

function boot_fcgi() {
	require_once WP_CLI_ROOT . '/vendor/adoy/fastcgi-client/src/Adoy/FastCGI/Client.php';

	$fcgi = new \Adoy\FastCGI\Client( 'localhost', '9000' );

	$content = http_build_query( $_SERVER['argv'] );

	$args = array(
		'GATEWAY_INTERFACE' => 'FastCGI/1.0',
		'REQUEST_METHOD'    => 'POST',
		'SCRIPT_FILENAME'   => '/usr/local/var/www/wp-cli/php/boot-fpm.php',
		'SCRIPT_NAME'       => '/',
		'SERVER_SOFTWARE'   => 'php/fcgiclient',
		'REMOTE_ADDR'       => '127.0.0.1',
		'REMOTE_PORT'       => '9000',
		'SERVER_NAME'       => 'localhost',
		'SERVER_PROTOCOL'   => 'HTTP/1.1',
		'CONTENT_TYPE'      => 'application/x-www-form-urlencoded',
		'CONTENT_LENGTH'    => strlen( $content ),
	);

	$response = $fcgi->request(
		$args,
		$content
	);

	list( $response_headers, $response_body ) = explode( "\r\n\r\n", $response, 2 );

	die( $response_body );
}

boot_fcgi();
