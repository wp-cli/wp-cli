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
		\WP_CLI::line( $prefix . $this->get_full_synopsis( false ) );
	}

	function get_shortdesc() {
		return $this->docparser->get_shortdesc();
	}

	function get_full_synopsis( $validate = true ) {
		$full_name = implode( ' ', get_path( $this ) );
		$synopsis = $this->get_synopsis();

		$tokens = \WP_CLI\SynopsisParser::parse( $synopsis );
		if ( isset( $tokens['unknown'] ) ) {
			foreach ( $tokens['unknown'] as $token ) {
				\WP_CLI::warning( sprintf( "Invalid token '%s' in synopsis for '%s'",
					$token, $full_name ) );
			}
		}

		return "$full_name $synopsis";
	}

	function get_synopsis() {
		return $this->docparser->get_synopsis();
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		call_user_func( $this->callable, $args, $assoc_args );
	}

	function get_name() {
		return $this->name;
	}

	function get_parent() {
		return $this->parent;
	}

	protected function check_args( $args, $assoc_args ) {
		$synopsis = $this->get_synopsis();
		if ( !$synopsis )
			return;

		$accepted_params = \WP_CLI\SynopsisParser::parse( $synopsis );

		$this->check_positional( $args, $accepted_params );

		$this->check_assoc( $assoc_args, $accepted_params );

		if ( empty( $accepted_params['generic'] ) )
			$this->check_unknown_assoc( $assoc_args, $accepted_params );
	}

	private function check_positional( $args, $accepted_params ) {
		$count = 0;

		foreach ( $accepted_params['positional'] as $param ) {
			if ( !$param['optional'] )
				$count++;
		}

		if ( count( $args ) < $count ) {
			$this->show_usage();
			exit(1);
		}
	}

	private function check_assoc( $assoc_args, $accepted_params ) {
		$mandatory_assoc = array();

		$assoc_args += \WP_CLI::get_config();

		foreach ( $accepted_params['assoc'] as $param ) {
			if ( !$param['optional'] )
				$mandatory_assoc[] = $param['name'];
		}

		$errors = array();

		foreach ( $mandatory_assoc as $key ) {
			if ( !isset( $assoc_args[ $key ] ) )
				$errors[] = "missing --$key parameter";
			elseif ( true === $assoc_args[ $key ] )
				$errors[] = "--$key parameter needs a value";
		}

		if ( !empty( $errors ) ) {
			foreach ( $errors as $error ) {
				\WP_CLI::warning( $error );
			}
			$this->show_usage();
			exit(1);
		}
	}

	private function check_unknown_assoc( $assoc_args, $accepted_params ) {
		$known_assoc = array();

		foreach ( array( 'assoc', 'flag' ) as $type ) {
			foreach ( $accepted_params[$type] as $param ) {
				$known_assoc[] = $param['name'];
			}
		}

		$unknown_assoc = array_diff( array_keys( $assoc_args ), $known_assoc );

		foreach ( $unknown_assoc as $key ) {
			\WP_CLI::warning( "unknown --$key parameter" );
		}
	}

}

