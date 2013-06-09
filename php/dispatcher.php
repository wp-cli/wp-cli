<?php

namespace WP_CLI\Dispatcher;

function get_path( Command $command ) {
	$path = array();

	do {
		array_unshift( $path, $command->get_name() );
	} while ( $command = $command->get_parent() );

	return $path;
}

function get_full_synopsis( $command, $validate = false ) {
	$subcommands = $command->get_subcommands();

	if ( empty( $subcommands ) ) {
		$synopsis = $command->get_synopsis();

		if ( $validate ) {
			$tokens = \WP_CLI\SynopsisParser::parse( $synopsis );

			foreach ( $tokens as $token ) {
				if ( 'unknown' == $token['type'] ) {
					\WP_CLI::warning( sprintf(
						"Invalid token '%s' in synopsis for '%s'",
						$token['token'], $full_name
					) );
				}
			}
		}

		$full_name = implode( ' ', get_path( $command ) );

		return "$full_name $synopsis";
	} else {
		return implode( "\n\n", array_map( __FUNCTION__,
			$subcommands ) );
	}
}


interface Command {

	function get_name();
	function get_parent();

	function get_synopsis();
	function get_shortdesc();

	function get_subcommands();

	function invoke( $args, $assoc_args );
	function show_usage();
}


interface CommandContainer {

	function add_subcommand( $name, Command $command );

	function find_subcommand( &$args );
	function pre_invoke( &$args );
}

