<?php

namespace WP_CLI\Dispatcher;

class Subcommand implements Command, Documentable {

	function __construct( $name, $callable, $docparser, $parent ) {
		$this->name = $name;
		$this->callable = $callable;
		$this->docparser = $docparser;
		$this->parent = $parent;
	}

	function show_usage( $prefix = 'usage: ' ) {
		\WP_CLI::line( $prefix . $this->get_full_synopsis() );
	}

	function get_shortdesc() {
		return $this->docparser->get_shortdesc();
	}

	function get_full_synopsis() {
		$full_name = implode( ' ', $this->get_path() );
		$synopsis = $this->docparser->get_synopsis();

		return "wp $full_name $synopsis";
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		call_user_func( $this->callable, $args, $assoc_args );
	}

	function get_subcommands() {
		return array();
	}

	function get_name() {
		return $this->name;
	}

	function get_path() {
		return array_merge( $this->parent->get_path(), array( $this->get_name() ) );
	}

	protected function check_args( $args, $assoc_args ) {
		$synopsis = $this->docparser->get_synopsis();
		if ( !$synopsis )
			return;

		$accepted_params = $this->parse_synopsis( $synopsis );

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

	protected function parse_synopsis( $synopsis ) {
		list( $patterns, $params ) = self::get_patterns();

		$tokens = preg_split( '/[\s\t]+/', $synopsis );

		foreach ( $tokens as $token ) {
			foreach ( $patterns as $regex => $desc ) {
				if ( preg_match( $regex, $token, $matches ) ) {
					$type = $desc['type'];
					$params[$type][] = array_merge( $matches, $desc );
					break;
				}
			}
		}

		return $params;
	}

	private static function get_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-z-|]+)';

		$param_types = array(
			array( 'positional', "<$p_value>",           1, 1 ),
			array( 'generic',    "--<field>=<value>",    1, 1 ),
			array( 'assoc',      "--$p_name=<$p_value>", 1, 1 ),
			array( 'flag',       "--$p_name",            1, 0 ),
		);

		$patterns = array();
		$params = array();

		foreach ( $param_types as $pt ) {
			list( $type, $pattern, $optional, $mandatory ) = $pt;

			if ( $mandatory ) {
				$patterns[ "/^$pattern$/" ] = array(
					'type' => $type,
					'optional' => false
				);
			}

			if ( $optional ) {
				$patterns[ "/^\[$pattern\]$/" ] = array(
					'type' => $type,
					'optional' => true
				);
			}

			$params[ $type ] = array();
		}

		return array( $patterns, $params );
	}
}

