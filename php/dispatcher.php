<?php

namespace WP_CLI\Dispatcher;

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
	function get_synopsis();

	function get_subcommands();

	function invoke( $args, $assoc_args );
	function show_usage();
}


interface CommandContainer {

	function add_subcommand( $name, Command $command );

	function find_subcommand( &$args );
	function pre_invoke( &$args );
}


interface Documentable {

	function get_shortdesc();
	function get_full_synopsis();
}

