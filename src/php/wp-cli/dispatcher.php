<?php

namespace WP_CLI\Dispatcher;

function traverse( &$args ) {
	$args_copy = $args;

	$command = \WP_CLI::$root;

	while ( !empty( $args ) && $command && $command instanceof Composite ) {
		$command = $command->find_subcommand( $args );
	}

	if ( !$command )
		$args = $args_copy;

	return $command;
}


interface Command {

	function get_path();
	function get_subcommands();

	function show_usage();
	function invoke( $arguments, $assoc_args );
}


interface Composite {

	function find_subcommand( &$arguments );
}


interface Documentable {

	function get_shortdesc();
	function get_synopsis();
}


class RootCommand implements Command, Composite {

	function get_path() {
		return array();
	}

	function show_usage() {
		\WP_CLI::line( 'Available commands:' );

		foreach ( \WP_CLI::load_all_commands() as $command ) {
			\WP_CLI::line( sprintf( "    wp %s %s",
				implode( ' ', $command->get_path() ),
				implode( '|', array_keys( $command->get_subcommands() ) )
			) );
		}

		\WP_CLI::line(<<<EOB

See 'wp help <command>' for more information on a specific command.

Global parameters:
--user=<id|login>   set the current user
--url=<url>         set the current URL
--path=<path>       set the current path to the WP install
--require=<path>    load a certain file before running the command
--quiet             suppress informational messages
--version           print wp-cli version
EOB
		);
	}

	function invoke( $arguments, $assoc_args ) {
		if ( empty( $arguments ) || array( 'help' ) == $arguments ) {
			$this->show_usage();
			exit;
		}

		$cmd_name = $arguments[0];
		$command = $this->find_subcommand( $arguments );

		if ( !$command )
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command. See 'wp help'.", $cmd_name ) );

		$command->invoke( $arguments, $assoc_args );
	}

	function find_subcommand( &$arguments ) {
		$command = array_shift( $arguments );

		$aliases = array(
			'sql' => 'db'
		);

		if ( isset( $aliases[ $command ] ) )
			$command = $aliases[ $command ];

		return \WP_CLI::load_command( $command );
	}

	function get_subcommands() {
		return \WP_CLI::load_all_commands();
	}
}


class CompositeCommand implements Command, Composite {

	function __construct( $name, $class ) {
		$this->name = $name;
		$this->class = $class;
	}

	function get_path() {
		return array( $this->name );
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

	function find_subcommand( &$args ) {
		$name = array_shift( $args );

		$subcommands = $this->get_subcommands();

		if ( !isset( $subcommands[ $name ] ) ) {
			$aliases = self::get_aliases( $subcommands );

			if ( isset( $aliases[ $name ] ) ) {
				$name = $aliases[ $name ];
			}
		}

		if ( !isset( $subcommands[ $name ] ) )
			return false;

		return $subcommands[ $name ];
	}

	private static function get_aliases( $subcommands ) {
		$aliases = array();

		foreach ( $subcommands as $name => $subcommand ) {
			$alias = $subcommand->get_alias();
			if ( $alias )
				$aliases[ $alias ] = $name;
		}

		return $aliases;
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


abstract class Subcommand implements Command, Documentable {

	function __construct( $method, $parent ) {
		$this->parent = $parent;

		$this->method = $method;
	}

	function show_usage( $prefix = 'usage: ' ) {
		$full_name = implode( ' ', $this->get_path() );
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $full_name $synopsis" );
	}

	function get_subcommands() {
		return array();
	}

	function get_path() {
		return array_merge( $this->parent->get_path(), array( $this->get_name() ) );
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
			\WP_CLI::warning( "unknown --$key parameter" );
		}
	}

	function get_shortdesc() {
		$comment = $this->method->getDocComment();

		if ( !preg_match( '/\* (\w.+)\n*/', $comment, $matches ) )
			return false;

		return $matches[1];
	}

	public function get_synopsis() {
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

	private function get_tag( $name ) {
		$comment = $this->method->getDocComment();

		if ( preg_match( '/@' . $name . '\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return false;
	}

	function get_name() {
		if ( $name = $this->get_tag( 'subcommand' ) )
			return $name;

		return $this->method->name;
	}

	function get_alias() {
		return $this->get_tag( 'alias' );
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$class = $this->parent->class;
		$instance = new $class;

		$this->method->invoke( $instance, $args, $assoc_args );
	}
}


class SingleCommand extends Subcommand {

	function __construct( $name, $callable, $parent ) {
		$this->name = $name;
		$this->callable = $callable;

		$method = new \ReflectionMethod( $this->callable, '__invoke' );

		parent::__construct( $method, $parent );
	}

	function get_name() {
		return $this->name;
	}

	function invoke( $args, $assoc_args ) {
		$this->check_args( $args, $assoc_args );

		$this->method->invoke( $this->callable, $args, $assoc_args );
	}
}

