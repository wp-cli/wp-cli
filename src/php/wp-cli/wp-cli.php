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
		if (isset($assoc_args['path'])) $docroot = $assoc_args['path'];
		else $docroot = './';
		WP_CLI::line('Downloading WordPress...');
		exec("curl http://wordpress.org/latest.zip > /tmp/wordpress.zip");
		exec("unzip /tmp/wordpress.zip");
		exec("mv wordpress/* $docroot");
		exec("rm -r wordpress");
		WP_CLI::success('WordPress downloaded.');
		exit;
	} else {
		WP_CLI::error('This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.');
		exit;
	}
}

if ( array( 'core', 'config' ) == $arguments ) {
	$_POST['dbname'] = $assoc_args['name'];
	$_POST['uname'] = $assoc_args['user'];
	$_POST['pwd'] = $assoc_args['pass'];
	$_POST['dbhost'] = isset( $assoc_args['host'] ) ? $assoc_args['host'] : 'localhost';
	$_POST['prefix'] = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : 'wp_';

	$_GET['step'] = 2;
	require WP_ROOT . '/wp-admin/setup-config.php';
	exit;
}

// Handle --url and --blog parameters
WP_CLI::_set_url();

// Implement --silent flag
if ( isset( $assoc_args['silent'] ) ) {
	define('WP_CLI_SILENT', true);
} else {
	define('WP_CLI_SILENT', false);
}

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
	include $filename;
}

// Load all plugin commands
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

