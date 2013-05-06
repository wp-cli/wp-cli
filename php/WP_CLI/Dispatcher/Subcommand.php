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

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return $this->parent;
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

	private function validate_args( $args, &$assoc_args ) {
		$synopsis = $this->get_synopsis();

		if ( !$synopsis )
			return;

		$parser = new \WP_CLI\SynopsisParser( $synopsis );
		if ( !$parser->enough_positionals( $args ) ) {
			$this->show_usage();
			exit(1);
		}

		$errors = $parser->validate_assoc( $assoc_args, array_keys( \WP_CLI::get_config() ) );

		if ( !empty( $errors['fatal'] ) ) {
			$out = '';
			foreach ( $errors['fatal'] as $error ) {
				$out .= "\n " . $error;
			}

			\WP_CLI::error( $out, "Parameter errors" );
		}

		array_map( '\\WP_CLI::warning', $errors['warning'] );

		foreach ( $parser->unknown_assoc( $assoc_args ) as $key ) {
			\WP_CLI::warning( "unknown --$key parameter" );
		}
	}

	function invoke( $args, $assoc_args ) {
		$this->validate_args( $args, $assoc_args );

		$instance = new $this->method->class;

		call_user_func( array( $instance, $this->method->name ), $args, $assoc_args );
	}
}

