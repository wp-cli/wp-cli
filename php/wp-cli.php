<?php

// Can be used by plugins/themes to check if WP-CLI is running or not
define( 'WP_CLI', true );
define( 'WP_CLI_VERSION', trim( file_get_contents( WP_CLI_ROOT . '/VERSION' ) ) );
define( 'WP_CLI_START_MICROTIME', microtime( true ) );

// Set common headers, to prevent warnings from plugins
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
$_SERVER['HTTP_USER_AGENT'] = '';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include WP_CLI_ROOT . '/php/utils.php';
include WP_CLI_ROOT . '/php/dispatcher.php';
include WP_CLI_ROOT . '/php/class-wp-cli.php';
include WP_CLI_ROOT . '/php/class-wp-cli-command.php';

\WP_CLI\Utils\load_dependencies();

// check for updates on shutdown with 1/50 probability
// disabled for Behat as we are limited to 60 request per hour
if ( getenv('BEHAT_RUN') && 0 === mt_rand( 0, 50 ) ) {
	register_shutdown_function( function() {
		// prevent unnecessary checks - we don't care what updates are available, only that there is an update
		if ( file_exists( \WP_CLI::get_home() . "/has-new-version" ) ) {
			return;
		}

		// prevent infinite loops
		if ( ! isset( $GLOBALS['argv'][1] ) || 'cli' !== $GLOBALS['argv'][1] || ! isset( $GLOBALS['argv'][2] ) || 'check-update' !== $GLOBALS['argv'][1] ) {

			// make sure that the HOME env variable is set
			$home = getenv( 'HOME' ) ? : getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
			$php_binary = \WP_CLI::get_php_binary();
			$wp_cli_path = realpath( $_SERVER['argv'][0] );
			$allow_root = \WP_CLI::get_runner()->config['allow-root'] ? '--allow-root' : '';

			// output must be redirected to /dev/null, otherwise this will block
			\WP_CLI\Process::create( "HOME=$home {$php_binary} {$wp_cli_path} cli check-update {$allow_root} > /dev/null 2>&1 &" )->run();
		}
	} );
}

WP_CLI::get_runner()->start();
