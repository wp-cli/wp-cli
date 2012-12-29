<?php

namespace WP_CLI\Dispatcher;

function traverse( &$args, $method = 'find_subcommand' ) {
	$args_copy = $args;

	$command = \WP_CLI::$root;

	while ( !empty( $args ) && $command && $command instanceof CommandContainer ) {
		$command = $command->$method( $args );
	}

	if ( !$command )
		$args = $args_copy;

	return $command;
}


interface Command {

	function get_path();
	function get_subcommands();

	function show_usage();
	function invoke( $args, $assoc_args );
}


interface CommandContainer {

	function show_usage();

	function find_subcommand( &$args );

	function pre_invoke( &$args );
}


interface Documentable {

	function get_shortdesc();
	function get_full_synopsis();
}

