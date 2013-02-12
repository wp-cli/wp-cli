<?php

namespace WP_CLI\Dispatcher;

class Subcommand implements Command, AtomicCommand, Documentable {

	function __construct( CommandContainer $parent, $name, $callable, $docparser ) {
		$this->parent = $parent;
		$this->name = $name;

		$this->callable = $callable;

		$this->docparser = $docparser;
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

		call_user_func( $this->callable, $args, $assoc_args );
	}

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return $this->parent;
	}
}

