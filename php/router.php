<?php
// Used by `wp server` to route requests.

require_once __DIR__ . '/router-lib.php';

WP_CLI\Router\add_filter( 'option_home', '\\WP_CLI\\Router\\option_home', 20 );
WP_CLI\Router\add_filter( 'option_siteurl', '\\WP_CLI\\Router\\option_siteurl', 20 );

$root = $_SERVER['DOCUMENT_ROOT'];
$path = '/'. ltrim( parse_url( urldecode( $_SERVER['REQUEST_URI'] ) )['path'], '/' );

if ( file_exists( $root.$path ) ) {
	if ( is_dir( $root.$path ) && substr( $path, -1 ) !== '/' ) {
		header( "Location: $path/" );
		exit;
	}

	if ( strpos( $path, '.php' ) !== false ) {
		chdir( dirname( $root.$path ) );
		require_once $root.$path;
	} else {
		return false;
	}
} else {
	chdir( $root );
	require_once 'index.php';
}
