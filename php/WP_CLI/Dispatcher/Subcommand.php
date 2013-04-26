<?php

namespace WP_CLI\Dispatcher;

class Subcommand implements Command, AtomicCommand, Documentable {

	private $parent, $name, $method, $docparser;

	function __construct( CommandContainer $parent, \ReflectionMethod $method, $name = false ) {
		$docparser = new \WP_CLI\DocParser( $method );

		if ( !$name )
			$name = $docparser->get_tag( 'subcommand' );

		if ( !$name )
			$name = $method->name;

		$this->parent = $parent;
		$this->name = $name;

		$this->method = $method;
		$this->docparser = $docparser;
	}

	function get_alias() {
		return $this->docparser->get_tag( 'alias' );
	}

	function show_usage( $prefix = 'usage: ' ) {
		\WP_CLI::line( $prefix . $this->get_full_synopsis() );
	}

	function get_shortdesc() {
		return $this->docparser->get_shortdesc();
	}

	function get_full_synopsis( $validate = false ) {
		$full_name = implode( ' ', get_path( $this ) );
		$synopsis = $this->get_synopsis();

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

		return "$full_name $synopsis";
	}

	function get_synopsis() {
		return $this->docparser->get_synopsis();
	}

	function invoke( $args, $assoc_args ) {
		$synopsis = $this->get_synopsis();

		if ( $synopsis ) {
			\WP_CLI\SynopsisParser::validate_args( $synopsis, $args, $assoc_args,
				array( $this, 'show_usage' ) );
		}

		$instance = new $this->method->class;

		call_user_func( array( $instance, $this->method->name ), $args, $assoc_args );
	}

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return $this->parent;
	}
}

