<?php

if ( PHP_SAPI !== 'cli' ) {
	die( 'Only cli access' );
}

define( 'WP_CLI_VERSION', '0.5.0-dev' );

// Define the wp-cli location
define( 'WP_CLI_ROOT', __DIR__ . '/' );

// Set a constant that can be used to check if we are running wp-cli or not
define( 'WP_CLI', true );

// Include the wp-cli classes
include WP_CLI_ROOT . 'class-wp-cli.php';
include WP_CLI_ROOT . 'class-wp-cli-command.php';

// Include the command line tools
include WP_CLI_ROOT . '../php-cli-tools/lib/cli/cli.php';

// Register the cli tools autoload
\cli\register_autoload();

// Get the cli arguments
list( $arguments, $assoc_args ) = WP_CLI::parse_args( array_slice( $GLOBALS['argv'], 1 ) );

// Handle --version parameter
if ( isset( $assoc_args['version'] ) ) {
	WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	exit;
}

// Define the WordPress location
if ( is_readable( $_SERVER['PWD'] . '/../wp-load.php' ) ) {
	define('WP_ROOT', $_SERVER['PWD'] . '/../');
} elseif (isset($assoc_args['path'])) {
	$root = (preg_match('@/$@', $assoc_args['path'])) ? $assoc_args['path'] : $assoc_args['path'] . "/";
	define('WP_ROOT', $root);
} else {
	define('WP_ROOT', $_SERVER['PWD'] . '/');
}

if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
	if ( array( 'core', 'download' ) == $arguments ) {
		include WP_CLI_ROOT.'/commands/internals/core.php';
		new WP_CLI::$commands['core']( $arguments, $assoc_args );
		exit;
	} else {
		WP_CLI::error('This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.');
		exit;
	}
}

if ( array( 'core', 'config' ) == $arguments ) {
	include WP_CLI_ROOT.'/commands/internals/core.php';
	new WP_CLI::$commands['core']( $arguments, $assoc_args );
	exit;
}

// Handle --url and --blog parameters
WP_CLI::_set_url();

// Check --silent flag
define( 'WP_CLI_SILENT', isset( $assoc_args['silent'] ) );

// Set installer flag before loading any WP files
if ( count( $arguments ) >= 2 && $arguments[0] == 'core' && $arguments[1] == 'install' ) {
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

// Load all internal commands
foreach ( glob(WP_CLI_ROOT.'/commands/internals/*.php') as $filename ) {
	include_once $filename;
}

// Load all community commands
foreach ( glob(WP_CLI_ROOT.'/commands/community/*.php') as $filename ) {
	include $filename;
}

// Handle --completions parameter
if ( isset( $assoc_args['completions'] ) ) {
	foreach ( WP_CLI::$commands as $name => $command ) {
		WP_CLI::line( $name .  ' ' . implode( ' ', WP_CLI_Command::get_subcommands($command) ) );
	}
	exit;
}

// Get the top-level command
if ( empty( $arguments ) )
	$command = 'help';
else
	$command = array_shift( $arguments );

// Translate aliases
$aliases = array(
	'sql' => 'db'
);

if ( isset( $aliases[ $command ] ) )
	$command = $aliases[ $command ];

if ( !isset( WP_CLI::$commands[$command] ) ) {
	WP_CLI::error( "'$command' is not a registered wp command. See 'wp help'." );
	exit;
}

new WP_CLI::$commands[$command]( $arguments, $assoc_args );

