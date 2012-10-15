<?php

namespace WP_CLI\Dispatcher;

interface Command {

	function show_usage();
	function invoke( $arguments, $assoc_args );
	function get_subcommands();
}


class CompositeCommand implements Command {

	function __construct( $name, $class ) {
		$this->name = $name;
		$this->class = $class;
	}

	function shortdesc() {
	}

	function show_usage() {
		$methods = $this->get_subcommands();

		$i = 0;

		foreach ( $methods as $name => $subcommand ) {
			$prefix = ( 0 == $i++ ) ? 'usage: ' : '   or: ';

			$subcommand->show_usage( $prefix );
		}

		\WP_CLI::line();
		\WP_CLI::line( "See 'wp help $this->name <subcommand>' for more information on a specific subcommand." );
	}

	function invoke( $args, $assoc_args ) {
		$subcommand = $this->find_subcommand( $args );

		if ( !$subcommand ) {
			$this->show_usage();
			return;
		}

		$subcommand->invoke( $args, $assoc_args );
	}

	private function find_subcommand( &$args ) {
		$class = $this->class;

		if ( empty( $args ) ) {
			$name = $class::get_default_subcommand();
		} else {
			$name = array_shift( $args );
		}

		$aliases = $class::get_aliases();

		if ( isset( $aliases[ $name ] ) ) {
			$name = $aliases[ $name ];
		}

		$subcommands = $this->get_subcommands();

		if ( !isset( $subcommands[ $name ] ) )
			return false;

		return $subcommands[ $name ];
	}

	public function get_subcommands() {
		$reflection = new \ReflectionClass( $this->class );

		$subcommands = array();

		foreach ( $reflection->getMethods() as $method ) {
			if ( !self::_is_good_method( $method ) )
				continue;

			$subcommand = new MethodSubcommand( $method, $this );

			$subcommands[ $subcommand->get_name() ] = $subcommand;
		}

		return $subcommands;
	}

	private static function _is_good_method( $method ) {
		return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
	}
}


abstract class Subcommand implements Command {

	function __construct( $method ) {
		$this->method = $method;
	}

	function get_subcommands() {
		return array();
	}

	abstract function get_name();

	protected function check_args( $args, $assoc_args ) {
		$synopsis = $this->get_synopsis();
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

		$assoc_args += \WP_CLI::get_assoc_special();

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
			\WP_CLI::warning( "unkown --$key parameter" );
		}
	}

	protected function get_synopsis() {
		$comment = $this->method->getDocComment();

		if ( !preg_match( '/@synopsis\s+([^\n]+)/', $comment, $matches ) )
			return false;

		return $matches[1];
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


class MethodSubcommand extends Subcommand {

	function __construct( $method, $parent ) {
		$this->parent = $parent;

		parent::__construct( $method );
	}

	function show_usage( $prefix = 'usage: ' ) {
		$command = $this->parent->name;
		$subcommand = $this->get_name();
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $command $subcommand $synopsis" );
	}

	function get_name() {
		$comment = $this->method->getDocComment();

		if ( preg_match( '/@subcommand\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return $this->method->name;
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$class = $this->parent->class;
		$instance = new $class;

		$this->method->invoke( $instance, $args, $assoc_args );
	}
}


class SingleCommand extends Subcommand {

	function __construct( $name, $callable ) {
		$this->name = $name;
		$this->callable = $callable;

		$method = new \ReflectionMethod( $this->callable, '__invoke' );

		parent::__construct( $method );
	}

	function get_name() {
		return $this->name;
	}

	function show_usage( $prefix = 'usage: ' ) {
		$command = $this->get_name();
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $command $synopsis" );
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$this->method->invoke( $this->callable, $args, $assoc_args );
	}
}

