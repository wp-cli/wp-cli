<?php

return array(
	'output' => array(
		'runtime' => '=<path>',
		'file' => '<path>',
		'desc' => 'Path to output file',
	),

	'version' => array(
		'runtime' => '=<version>',
		'file' => '<version>',
		'desc' => 'New package version',
	),

	'store-version' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'If true the contents of ./VERSION will be set to the value passed to --version',
	),

	'quiet' => array(
		'runtime' => '',
		'file' => '<bool>',
		'default' => false,
		'desc' => 'Suppress informational messages',
	),
);

