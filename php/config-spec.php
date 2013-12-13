<?php

return array(
	'skip-plugin' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'desc' => 'Skip loading plugin (plugin path name starting from .../wp-content/plugins/; may be used more than once)',
		'multiple' => true,
		'default' => array(),
	),
	
	'path' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'desc' => 'Path to the WordPress files',
	),

	'url' => array(
		'runtime' => '=<url>',
		'file' => '<url>',
		'desc' => 'Pretend request came from given URL',
	),

	'blog' => array(
		'deprecated' => 'Use --url instead.',
		'runtime' => '=<url>',
	),

	'config' => array(
		'deprecated' => 'Use the WP_CLI_CONFIG_PATH environment variable instead.',
		'runtime' => '=<path>',
	),

	'user' => array(
		'runtime' => '=<id|login>',
		'file' => '<id|login>',
		'desc' => 'Set the WordPress user',
	),

	'require' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'desc' => 'Load PHP file before running the command (may be used more than once)',
		'multiple' => true,
		'default' => array(),
	),

	'disabled_commands' => array(
		'file' => '<list>',
		'default' => array(),
		'desc' => '(Sub)commands to disable',
	),

	'color' => array(
		'runtime' => true,
		'file' => '<bool>',
		'default' => 'auto',
		'desc' => 'Whether to colorize the output',
	),

	'debug' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Show all PHP errors',
	),

	'prompt' => array(
		'runtime' => '',
		'file' => false,
		'default' => false,
		'desc' => 'Prompt the user to enter values for all command arguments',
	),

	'quiet' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Suppress informational messages',
	),

	'apache_modules' => array(
		'file' => '<list>',
		'desc' => 'List of Apache Modules that are to be reported as loaded',
		'multiple' => true,
		'default' => array(),
	),
);

