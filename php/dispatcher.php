<?php

namespace WP_CLI\Dispatcher;

function get_subcommands( $command ) {
	if ( $command instanceof CommandContainer )
		return $command->get_subcommands();

	return array();
}

function get_path( Command $command ) {
	$path = array();

	do {
		array_unshift( $path, $command->get_name() );
	} while ( $command = $command->get_parent() );

	return $path;
}


interface Command {

	function get_name();
	function get_parent();

	function show_usage();
}


interface AtomicCommand {

	function get_synopsis();
	function invoke( $args, $assoc_args );
}


interface CommandContainer {

	function add_subcommand( $name, Command $command );
	function get_subcommands();

	function find_subcommand( &$args );
	function pre_invoke( &$args );
}


interface Documentable {

	function get_shortdesc();
	function get_full_synopsis();
}

