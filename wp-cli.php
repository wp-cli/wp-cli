#!/usr/bin/env php
<?php

if(PHP_SAPI !== 'cli') {
	die('Only cli access');
}

define( 'WP_CLI_VERSION', '0.2' );

// Define the wp-cli location
define('WP_CLI_ROOT', __DIR__ . '/');

// Set a constant that can be used to check if we are running wp-cli or not
define('WP_CLI', true);

// Include the wp-cli classes
include WP_CLI_ROOT.'class-wp-cli.php';
include WP_CLI_ROOT.'class-wp-cli-command.php';

// Include the command line tools
include WP_CLI_ROOT.'php-cli-tools/lib/cli/cli.php';
\cli\register_autoload();

// Get the cli arguments
list( $arguments, $assoc_args ) = WP_CLI::parse_args( array_slice( $GLOBALS['argv'], 1 ) );

// Handle --version parameter
if ( isset( $assoc_args['version'] ) ) {
	WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	exit;
}

// Define the WordPress location
if(is_readable($_SERVER['PWD'] . '/../wp-load.php')) {
	define('WP_ROOT', $_SERVER['PWD'] . '/../');
}
else {
	define('WP_ROOT', $_SERVER['PWD'] . '/');
}

// Taken from https://github.com/88mph/wpadmin/blob/master/wpadmin.php
if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
	WP_CLI::error('Either this is not a WordPress document root or you do not have permission to administer this site.');
	exit();
}

// Handle --blog parameter
if ( isset( $assoc_args['blog'] ) ) {
	$blog = $assoc_args['blog'];
	unset( $assoc_args['blog'] );
} elseif ( is_readable( WP_ROOT . '/wp-cli-blog' ) ) {
	$blog = file_get_contents( WP_ROOT . '/wp-cli-blog' );
}

if ( isset( $blog ) ) {
	list( $domain, $path ) = explode( '/', $blog, 2 );

	$_SERVER['HTTP_HOST'] = $domain;

	$_SERVER['REQUEST_URI'] = '/' . $path;
}

// Load WordPress libs
require_once(WP_ROOT . 'wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Load all internal commands
foreach (glob(WP_CLI_ROOT.'/commands/internals/*.php') as $filename)  {
	include $filename;
}

// Load all plugin commands
foreach (glob(WP_CLI_ROOT.'/commands/community/*.php') as $filename)  {
	include $filename;
}

// Check if there are commands installed
if(empty(WP_CLI::$commands)) {
	WP_CLI::error('No commands installed');
	WP_CLI::line();
	WP_CLI::line('Visit the wp-cli page on github on more information on how to install commands.');
	exit();
}

// Handle --completions parameter
if ( isset( $assoc_args['completions'] ) ) {
	foreach ( WP_CLI::$commands as $name => $command ) {
		WP_CLI::line( $name .  ' ' . implode( ' ', WP_CLI_Command::get_methods($command) ) );
	}
	exit;
}

// Get the top-level command
if ( empty( $arguments ) )
	$command = 'help';
else
	$command = array_shift( $arguments );

if ( !isset( WP_CLI::$commands[$command] ) ) {
	WP_CLI::error( "'$command' is not a registered wp command. See 'wp help'." );
	exit;
}

new WP_CLI::$commands[$command]( $command, $arguments, $assoc_args );

