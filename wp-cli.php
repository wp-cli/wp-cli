#!/usr/bin/env php
<?php

if(PHP_SAPI !== 'cli') {
	die('Only cli access');
}

define('WP_ROOT', '../wordpress/');

include 'class-wp-cli.php';
include 'class-wp-cli-command.php';

// Taken from https://github.com/88mph/wpadmin/blob/master/wpadmin.php

// Does the user have access to read the directory? If so, allow them to use the
// command line tool.

if(true == is_readable(WP_ROOT . 'wp-load.php')){
    // Load WordPress libs.
    require_once(WP_ROOT . 'wp-load.php');
    require_once(ABSPATH . WPINC . '/template-loader.php');
    require_once(ABSPATH . 'wp-admin/includes/admin.php');
}
else {
    die("Either this is not a WordPress document root or you do not have permission to administer this site. \n");
}

foreach (glob('library/commands/*.php') as $filename)  {
    include $filename;
}

// Start output buffering to stop WordPress from spitting out its usual output.

// Remove the first entry
$arguments = array_shift($GLOBALS['argv']);

// Get the command
$command = array_shift($GLOBALS['argv']);

//
if(isset(WP_CLI::$commands[$command])) {
	new WP_CLI::$commands[$command]($GLOBALS['argv']);
}
else {
	echo 'Unknown command'."\n";
}
