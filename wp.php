#!/usr/bin/env php
<?php

if(PHP_SAPI !== 'cli') {
	die('Only cli access');
}

include 'engine/command.php';
include 'engine/config.php';
include 'engine/functions.php';

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
