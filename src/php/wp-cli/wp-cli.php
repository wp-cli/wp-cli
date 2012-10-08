<?php

if ( PHP_SAPI !== 'cli' ) {
	echo "Only CLI access.\n";
	die(-1);
}

define( 'WP_CLI_VERSION', '0.7.0-alpha' );

// Define the wp-cli location
define( 'WP_CLI_ROOT', __DIR__ . '/' );

// Set a constant that can be used to check if we are running wp-cli or not
define( 'WP_CLI', true );

// Include the wp-cli classes
include WP_CLI_ROOT . 'dispatcher.php';
include WP_CLI_ROOT . 'class-wp-cli.php';
include WP_CLI_ROOT . 'class-wp-cli-command.php';
include WP_CLI_ROOT . 'class-wp-cli-command-with-meta.php';
include WP_CLI_ROOT . 'class-wp-cli-command-with-upgrade.php';

// Include the command line tools
include WP_CLI_ROOT . '../php-cli-tools/lib/cli/cli.php';

// Register the cli tools autoload
\cli\register_autoload();

// Get the cli arguments
list( $arguments, $assoc_args ) = WP_CLI::parse_args( array_slice( $GLOBALS['argv'], 1 ) );

// Set output levels
define( 'WP_CLI_AUTOCOMPLETE', isset( $assoc_args['completions'] ) );
define( 'WP_CLI_QUIET', isset( $assoc_args['quiet'] ) || isset( $assoc_args['silent'] ) );

// Handle --version parameter
if ( isset( $assoc_args['version'] ) && empty( $arguments ) ) {
	WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	exit;
}

// Handle --help parameter
if ( isset( $assoc_args['help'] ) ) {
	array_unshift( $arguments, 'help' );
	unset( $assoc_args['help'] );
}

$_SERVER['DOCUMENT_ROOT'] = getcwd();

// Define the WordPress location
if ( !empty( $assoc_args['path'] ) ) {
	// trailingslashit() isn't available yet
	define( 'WP_ROOT', rtrim( $assoc_args['path'], '/' ) . '/' );
} else {
	define( 'WP_ROOT', $_SERVER['PWD'] . '/' );
}

// Handle --url and --blog parameters
WP_CLI::_set_url( $assoc_args );

if ( array( 'core', 'download' ) == $arguments ) {
	WP_CLI::run_command( $arguments, $assoc_args );
	exit;
}

if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
	WP_CLI::error( 'This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.' );
}

if ( array( 'core', 'config' ) == $arguments ) {
	WP_CLI::run_command( $arguments, $assoc_args );
	exit;
}

// The db commands don't need any WP files
if ( array( 'db' ) == array_slice( $arguments, 0, 1 ) ) {
	WP_CLI::load_wp_config();
	WP_CLI::run_command( $arguments, $assoc_args );
	exit;
}

// Set installer flag before loading any WP files
if ( array( 'core', 'install' ) == $arguments ) {
	WP_CLI::check_required_args( array( 'url', 'title', 'admin_email', 'admin_password' ), $assoc_args );

    define( 'WP_INSTALLING', true );
}

// Load WordPress
require WP_ROOT . 'wp-load.php';

// Fix memory limit. See http://core.trac.wordpress.org/ticket/14889
@ini_set( 'memory_limit', -1 );

require ABSPATH . 'wp-admin/includes/admin.php';

// Load the right info into the global wp_query
if ( !defined( 'WP_INSTALLING' ) && isset( $assoc_args['url'] ) ) {
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

// Generate strings for autocomplete
if ( WP_CLI_AUTOCOMPLETE ) {
	foreach ( WP_CLI::load_all_commands() as $name => $command ) {
		$subcommands = implode( ' ', WP_CLI\Dispatcher\get_subcommands( $command ) );
		WP_CLI::line( $name .  ' ' . $subcommands );
	}
	exit;
}

WP_CLI::run_command( $arguments, $assoc_args );

