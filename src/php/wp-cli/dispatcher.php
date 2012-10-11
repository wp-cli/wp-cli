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
	$subcommand = find_subcommand( $class, $args );

	if ( !$subcommand ) {
		describe_command( $class, WP_CLI_COMMAND );
		return;
	}

	$instance = new $class;

	$subcommand->invoke( $instance, $args, $assoc_args );
}

function find_subcommand( $class, $args ) {
	if ( empty( $args ) ) {
		$name = $class::get_default_subcommand();
	} else {
		$name = array_shift( $args );
	}

	$aliases = $class::get_aliases();

	if ( isset( $aliases[ $name ] ) ) {
		$name = $aliases[ $name ];
	}

	$subcommands = get_subcommands( $class );

	if ( !isset( $subcommands[ $name ] ) )
		return false;

	return $subcommands[ $name ];
}

function describe_command( $class, $command ) {
	if ( method_exists( $class, 'help' ) ) {
		$class::help();
		return;
	}

	$methods = get_subcommands( $class );

	if ( empty( $methods ) ) {
		\WP_CLI::line(  "usage: wp $command" );
		return;
	}

	$i = 0;

	foreach ( $methods as $name => $subcommand ) {
		$prefix = ( 0 == $i++ ) ? 'usage:' : '   or:';

		\WP_CLI::line( "$prefix wp $command $name " . $subcommand->get_synopsis() );
	}

	\WP_CLI::line();
	\WP_CLI::line( "See 'wp help $command <subcommand>' for more information on a specific subcommand." );
}

/**
 * Get the list of subcommands for a class.
 *
 * @param string $class
 * @return array('subcommand' => Subcommand) The list of subcommands
 */
function get_subcommands( $class ) {
	if ( !is_string( $class ) )
		return array();

	$reflection = new \ReflectionClass( $class );

	$subcommands = array();

	foreach ( $reflection->getMethods() as $method ) {
		if ( !_is_good_method( $method ) )
			continue;

		$subcommand = new Subcommand( $method );

		$subcommands[ $subcommand->get_name() ] = $subcommand;
	}

	return $subcommands;
}

function _is_good_method( $method ) {
	return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
}


class Subcommand {

	function __construct( $method ) {
		$this->method = $method;
	}

	function get_name() {
		$comment = $this->method->getDocComment();

		if ( preg_match( '/@subcommand\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return $this->method->name;
	}

	function get_synopsis() {
		$comment = $this->method->getDocComment();

		if ( !preg_match( '/@synopsis\s+([^\n]+)/', $comment, $matches ) )
			return false;

		return $matches[1];
	}

	function invoke() {
		return call_user_func_array( array( $this->method, 'invoke' ), func_get_args() );
	}
}

