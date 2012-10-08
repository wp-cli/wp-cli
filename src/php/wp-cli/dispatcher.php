<?php

namespace WP_CLI\Dispatcher;

function dispatch( $implementation, $arguments, $assoc_args ) {
	if ( is_string( $implementation ) && class_exists( $implementation ) )
		dispatch_subcommand( $implementation, $arguments, $assoc_args );
	else
		call_user_func( $implementation, $arguments, $assoc_args );
}

/**
 * Transfers the handling to the appropriate method
 *
 * @param array $args
 * @param array $assoc_args
 */
function dispatch_subcommand( $class, $args, $assoc_args ) {
	if ( empty( $args ) ) {
		$subcommand = $class::get_default_subcommand();
	} else {
		$subcommand = array_shift( $args );
	}

	$method = subcommand_to_method( $class, $subcommand );

	if ( !$method ) {
		describe_command( $class, WP_CLI_COMMAND );
		return;
	}

	$instance = new $class;

	$method->invoke( $instance, $args, $assoc_args );
}

function subcommand_to_method( $class, $subcommand ) {
	$aliases = $class::get_aliases();

	if ( isset( $aliases[ $subcommand ] ) ) {
		$subcommand = $aliases[ $subcommand ];
	}

	$subcommands = get_subcommands( $class );

	if ( !isset( $subcommands[ $subcommand ] ) )
		return false;

	return $subcommands[ $subcommand ];
}

function describe_command( $class, $command ) {
	if ( method_exists( $class, 'help' ) ) {
		$class::help();
		return;
	}

	$methods = array_keys( get_subcommands( $class ) );

	$out = "usage: wp $command";

	if ( empty( $methods ) ) {
		\WP_CLI::line( $out );
	} else {
		$out .= ' [' . implode( '|', $methods ) . ']';

		\WP_CLI::line( $out );

		\WP_CLI::line();
		\WP_CLI::line( "See 'wp help $command <subcommand>' for more information on a specific subcommand." );
	}
}

/**
 * Get the list of subcommands for a class (reverse-dispatch).
 *
 * @param string $class
 * @return array('subcommand' => $method) The list of methods
 */
function get_subcommands( $class ) {
	if ( !is_string( $class ) )
		return array();

	$reflection = new \ReflectionClass( $class );

	$methods = array();

	foreach ( $reflection->getMethods() as $method ) {
		if ( !_is_good_method( $method ) )
			continue;

		$methods[ _get_subcommand_name( $method ) ] = $method;
	}

	return $methods;
}

function _is_good_method( $method ) {
	return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
}

function _get_subcommand_name( $method ) {
	$comment = $method->getDocComment();

	if ( preg_match( '/@subcommand\s+([a-z-]+)/', $comment, $matches ) )
		return $matches[1];

	return $method->name;
}

