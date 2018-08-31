<?php

if ( ! class_exists( 'CLI_Command' ) ) {
	require_once __DIR__ . '/src/CLI_Command.php';
}

if ( ! class_exists( 'Alias_Command' ) ) {
	require_once __DIR__ . '/src/Alias_Command.php';
}

WP_CLI::add_command( 'cli', 'CLI_Command' );
WP_CLI::add_command( 'alias', 'Alias_Command' );
