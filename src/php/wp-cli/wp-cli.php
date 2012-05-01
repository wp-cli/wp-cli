<?php

if ( PHP_SAPI !== 'cli' ) {
	die( 'Only cli access' );
}

define( 'WP_CLI_VERSION', '0.5.0' );

// Define the wp-cli location
define( 'WP_CLI_ROOT', __DIR__ . '/' );

// Set a constant that can be used to check if we are running wp-cli or not
define( 'WP_CLI', true );

// Include the wp-cli classes
include WP_CLI_ROOT . 'class-wp-cli.php';
include WP_CLI_ROOT . 'class-wp-cli-command.php';
include WP_CLI_ROOT . 'class-wp-cli-command-with-upgrade.php';

// Include the command line tools
include WP_CLI_ROOT . '../php-cli-tools/lib/cli/cli.php';

// Register the cli tools autoload
\cli\register_autoload();

// Get the cli arguments
list( $arguments, $assoc_args ) = WP_CLI::parse_args( array_slice( $GLOBALS['argv'], 1 ) );

// Check --silent flag
define( 'WP_CLI_SILENT', isset( $assoc_args['silent'] ) );

// Handle --version parameter
if ( isset( $assoc_args['version'] ) && empty( $arguments ) ) {
	WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	exit;
}

// Define the WordPress location
if ( is_readable( $_SERVER['PWD'] . '/../wp-load.php' ) ) {
	define( 'WP_ROOT', $_SERVER['PWD'] . '/../' );
} elseif ( !empty( $assoc_args['path'] ) ) {
	// trailingslashit() isn't available yet
	define( 'WP_ROOT', rtrim( $assoc_args['path'], '/' ) . '/' );
} else {
	define( 'WP_ROOT', $_SERVER['PWD'] . '/' );
}

if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
	if ( array( 'core', 'download' ) == $arguments ) {
		WP_CLI::run_command( $arguments, $assoc_args );
	} else {
		WP_CLI::error('This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.');
		exit;
	}
}

if ( array( 'core', 'config' ) == $arguments ) {
	WP_CLI::run_command( $arguments, $assoc_args );
}

if ( array( 'db', 'create' ) == $arguments ) {
	WP_CLI::load_wp_config();
	WP_CLI::run_command( $arguments, $assoc_args );
}

// Handle --url and --blog parameters
WP_CLI::_set_url( $assoc_args );

// Set installer flag before loading any WP files
if ( array( 'core', 'install' ) == $arguments ) {
    define( 'WP_INSTALLING', true );
}

// Load WordPress
require WP_ROOT . 'wp-load.php';
require ABSPATH . 'wp-admin/includes/admin.php';

// Load the right info into the global wp_query
if ( isset( $assoc_args['url'] ) ) {
    if ( isset( $GLOBALS['wp_query'] ) && isset( $GLOBALS['wp'] ) ) {
        $GLOBALS['wp']->parse_request();
        $GLOBALS['wp_query']->query($GLOBALS['wp']->query_vars);
    }
}

// Set filesystem method
add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

// Handle --user parameter
if ( isset( $assoc_args['user'] ) ) {
	$user = $assoc_args['user'];
	if ( is_numeric( $user ) ) {
		$user_id = (int) $user;
	} else {
		$user_id = (int) username_exists( $user );
	}
	if ( !$user_id || !wp_set_current_user( $user_id ) ) {
		WP_CLI::error( sprintf( 'Could not get a user_id for this user: %s', var_export( $user, true ) ) );
	}
	unset( $assoc_args['user'], $user );
}

// Handle --require parameter
if ( isset( $assoc_args['require'] ) ) {
	require $assoc_args['require'];
	unset( $assoc_args['require'] );
}

// Handle --completions parameter
if ( isset( $assoc_args['completions'] ) ) {
	foreach ( WP_CLI::load_all_commands() as $name => $command ) {
		WP_CLI::line( $name .  ' ' . implode( ' ', WP_CLI_Command::get_subcommands($command) ) );
	}
	exit;
}

WP_CLI::run_command( $arguments, $assoc_args );

