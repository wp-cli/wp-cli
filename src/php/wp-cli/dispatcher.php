<?php

namespace WP_CLI\Dispatcher;

function traverse( &$args, $method = 'find_subcommand' ) {
	$args_copy = $args;

	$command = \WP_CLI::$root;

	while ( !empty( $args ) && $command && $command instanceof Composite ) {
		$command = $command->$method( $args );
	}

	if ( !$command )
		$args = $args_copy;

	return $command;
}


interface Command {

	function get_path();
	function get_subcommands();

	function show_usage();
	function invoke( $args, $assoc_args );
}


interface Composite {

	function pre_invoke( &$args );
	function find_subcommand( &$args );
}


interface Documentable {

	function get_shortdesc();
	function get_synopsis();
}


class RootCommand implements Command, Composite {

	protected $subcommands = array();

	function get_path() {
		return array();
	}

	function show_usage() {
		\WP_CLI::line( 'Available commands:' );

		foreach ( $this->get_subcommands() as $command ) {
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

	function invoke( $args, $assoc_args ) {
		$subcommand = $this->pre_invoke( $args );
		$subcommand->invoke( $args, $assoc_args );
	}

	function pre_invoke( &$args ) {
		if ( empty( $args ) || array( 'help' ) == $args ) {
			$this->show_usage();
			exit;
		}

		$cmd_name = $args[0];
		$command = $this->find_subcommand( $args );

		if ( !$command )
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command. See 'wp help'.", $cmd_name ) );

		return $command;
	}

	function find_subcommand( &$args ) {
		$command = array_shift( $args );

		$aliases = array(
			'sql' => 'db'
		);

		if ( isset( $aliases[ $command ] ) )
			$command = $aliases[ $command ];

		return $this->load_command( $command );
	}

	function add_command( $name, $implementation ) {
		if ( is_string( $implementation ) )
			$command = new CompositeCommand( $name, $implementation );
		else {
			$method = new \ReflectionMethod( $implementation, '__invoke' );

			$command = new Subcommand( $name, $implementation, $method, $this );
		}

		$this->subcommands[ $name ] = $command;
	}

	function get_subcommands() {
		$this->load_all_commands();

		return $this->subcommands;
	}

	protected function load_all_commands() {
		foreach ( array( 'internals', 'community' ) as $dir ) {
			foreach ( glob( WP_CLI_ROOT . "/commands/$dir/*.php" ) as $filename ) {
				$command = substr( basename( $filename ), 0, -4 );

				if ( isset( $this->subcommands[ $command ] ) )
					continue;

				include $filename;
			}
		}
	}

	function load_command( $command ) {
		if ( !isset( $this->subcommands[$command] ) ) {
			foreach ( array( 'internals', 'community' ) as $dir ) {
				$path = WP_CLI_ROOT . "/commands/$dir/$command.php";

				if ( is_readable( $path ) ) {
					include $path;
					break;
				}
			}
		}

		if ( !isset( $this->subcommands[$command] ) ) {
			return false;
		}

		return $this->subcommands[$command];
	}
}


class CompositeCommand implements Command, Composite {

	protected $name;

	protected $subcommands;

	public function __construct( $name, $class ) {
		$this->name = $name;

		$this->subcommands = $this->collect_subcommands( $class );
	}

	private function collect_subcommands( $class ) {
		$reflection = new \ReflectionClass( $class );

		$subcommands = array();

		foreach ( $reflection->getMethods() as $method ) {
			if ( !self::_is_good_method( $method ) )
				continue;

			$subcommand = new MethodSubcommand( $class, $method, $this );

			$subcommands[ $subcommand->get_name() ] = $subcommand;
		}

		return $subcommands;
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
		$subcommand = $this->pre_invoke( $args );
		$subcommand->invoke( $args, $assoc_args );
	}

	function pre_invoke( &$args ) {
		$subcommand = $this->find_subcommand( $args );

		if ( !$subcommand ) {
			$this->show_usage();
			exit;
		}

		return $subcommand;
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
		return $this->subcommands;
	}

	private static function _is_good_method( $method ) {
		return $method->isPublic() && !$method->isConstructor() && !$method->isStatic();
	}
}


class Subcommand implements Command, Documentable {

	function __construct( $name, $callable, $method, $parent ) {
		$this->name = $name;
		$this->callable = $callable;
		$this->method = $method;
		$this->parent = $parent;
	}

	function show_usage( $prefix = 'usage: ' ) {
		$full_name = implode( ' ', $this->get_path() );
		$synopsis = $this->get_synopsis();

		\WP_CLI::line( $prefix . "wp $full_name $synopsis" );
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

	function __construct( $class, $method, $parent ) {
		$callable = array( new $class, $method->name );

		parent::__construct( self::_get_name( $method ), $callable, $method, $parent );
	}

	private static function _get_name( $method ) {
		if ( $name = self::get_tag( $method, 'subcommand' ) )
			return $name;

		return $method->name;
	}

	private static function get_tag( $method, $name ) {
		$comment = $method->getDocComment();

		if ( preg_match( '/@' . $name . '\s+([a-z-]+)/', $comment, $matches ) )
			return $matches[1];

		return false;
	}

	function get_alias() {
		return self::get_tag( $this->method, 'alias' );
	}
}

