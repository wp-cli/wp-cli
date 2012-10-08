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
		$subcommand = subcommand_to_method( $class, array_shift( $args ) );
	}

	if ( !$subcommand ) {
		describe_command( $class, WP_CLI_COMMAND );
		return;
	}

	$instance = new $class;
	$instance->$subcommand( $args, $assoc_args );
}

function subcommand_to_method( $class, $command ) {
	$aliases = $class::get_aliases();

	if ( isset( $aliases[ $subcommand ] ) ) {
		$method = $aliases[ $subcommand ];
	}

	if ( !method_exists( $class, $subcommand ) ) {
		// This if for reserved keywords in php (like list, isset)
		$subcommand = '_' . $subcommand;
	}

	if ( !method_exists( $class, $method ) ) {
		return false;
	}
}

function describe_command( $class, $command ) {
	if ( method_exists( $class, 'help' ) ) {
		$class::help();
		return;
	}

	$methods = get_subcommands( $class );

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
 * @return array The list of methods
 */
function get_subcommands( $class ) {
	if ( !is_string( $class ) )
		return array();

	$reflection = new \ReflectionClass( $class );

	return _filter_methods( $reflection, function( $method ) {
		$name = $method->name;

		if ( strpos( $name, '_' ) === 0 ) {
			$name = substr( $name, 1 );
		}

		return $name;
	} );
}

function _filter_methods( $reflection, $cb ) {
	$methods = array();

	foreach ( $reflection->getMethods() as $method ) {
		if ( !$method->isPublic() || $method->isStatic() || $method->isConstructor() )
			continue;

		$methods[] = $cb( $method );
	}

	return $methods;
}

