<?php

return array(
	'path' => array(
		'runtime' => true,
		'file' => true,
		'default' => null,
		'desc' => 'Path to the WordPress files',
		'synopsis' => '<path>'
	),

	'url' => array(
		'runtime' => true,
		'file' => true,
		'default' => null,
		'desc' => 'Pretend request came from given URL',
		'synopsis' => '<url>'
	),
	'blog' => array(
		'deprecated' => 'Use --url instead',
		'runtime' => true,
		'file' => false,
		'default' => null,
		'synopsis' => '<url>',
	),

	'user' => array(
		'runtime' => true,
		'file' => true,
		'default' => null,
		'desc' => 'Set the WordPress user',
		'synopsis' => '<id|login>'
	),

	'require' => array(
		'runtime' => true,
		'file' => true,
		'default' => null,
		'desc' => 'Load given PHP file before running the command',
		'synopsis' => '<path>'
	),

	'disabled_commands' => array(
		'runtime' => false,
		'file' => true,
		'default' => array(),
		'desc' => '(Sub)commands to disable',
	),

	'color' => array(
		'runtime' => true,
		'file' => true,
		'default' => false,
		'desc' => 'Show all PHP errors.',
		'synopsis' => '<bool>',
	),

	'debug' => array(
		'runtime' => true,
		'file' => true,
		'default' => false,
		'desc' => 'Show all PHP errors.',
		'synopsis' => '',
	),

	'quiet' => array(
		'runtime' => true,
		'file' => true,
		'default' => false,
		'desc' => 'Show all PHP errors.',
		'synopsis' => '<bool>',
	),
);

