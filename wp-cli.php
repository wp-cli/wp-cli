#!/usr/bin/env php
<?php

if(PHP_SAPI !== 'cli') {
	die('Only cli access');
}

// Define the WordPress location
if(is_readable($_SERVER['PWD'] . '/../wp-load.php')) {
	define('WP_ROOT', $_SERVER['PWD'] . '/../');
}
else {
	define('WP_ROOT', $_SERVER['PWD'] . '/');
}

// Define the wp-cli location
define('WP_CLI_ROOT', __DIR__ . '/');

// Set a constant that can be used to check if we are running wp-cli or not
define('WP_CLI', true);

// Include the wp-cli classes
include WP_CLI_ROOT.'class-wp-cli.php';
include WP_CLI_ROOT.'class-wp-cli-command.php';

// Include the command line tools, taken from here: https://github.com/jlogsdon/php-cli-tools
include WP_CLI_ROOT.'php-cli-tools/lib/cli/cli.php';
\cli\register_autoload();

// Taken from https://github.com/88mph/wpadmin/blob/master/wpadmin.php
// Does the user have access to read the directory? If so, allow them to use the command line tool.
if(true == is_readable(WP_ROOT . 'wp-load.php')){
	// Load WordPress libs.
	require_once(WP_ROOT . 'wp-load.php');
	require_once(ABSPATH . WPINC . '/template-loader.php');
	require_once(ABSPATH . 'wp-admin/includes/admin.php');
}
else {
	WP_CLI::error('Either this is not a WordPress document root or you do not have permission to administer this site.');
	exit();
}

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

// Get the cli arguments
$arguments = $GLOBALS['argv'];

// Remove the first entry
array_shift($arguments);

// Get the command
$command = array_shift($arguments);

// Try to load the class, otherwise it's an Unknown command
if(isset(WP_CLI::$commands[$command])) {
	new WP_CLI::$commands[$command]($arguments);
}
// Show the general help for wp-cli
else {
	WP_CLI::generalHelp();
}
