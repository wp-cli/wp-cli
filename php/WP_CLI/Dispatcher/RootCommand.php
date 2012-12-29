<?php

namespace WP_CLI\Dispatcher;

class RootCommand implements CommandContainer {

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
--info              print wp-cli information
EOB
		);
	}

	function pre_invoke( &$args ) {
		if ( array( 'help' ) == $args ) {
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

			$docparser = new \WP_CLI\DocParser( $method );

			$command = new Subcommand( $name, $implementation, $docparser, $this );
		}

		$this->subcommands[ $name ] = $command;
	}

	function get_subcommands() {
		$this->load_all_commands();

		ksort( $this->subcommands );

		return $this->subcommands;
	}

	protected function load_all_commands() {
		foreach ( glob( WP_CLI_ROOT . "/commands/*.php" ) as $filename ) {
			$command = substr( basename( $filename ), 0, -4 );

			if ( isset( $this->subcommands[ $command ] ) )
				continue;

			include $filename;
		}
	}

	function load_command( $command ) {
		if ( !isset( $this->subcommands[$command] ) ) {
			$path = WP_CLI_ROOT . "/commands/$command.php";

			if ( is_readable( $path ) ) {
				include $path;
			}
		}

		if ( !isset( $this->subcommands[$command] ) ) {
			return false;
		}

		return $this->subcommands[$command];
	}
}

