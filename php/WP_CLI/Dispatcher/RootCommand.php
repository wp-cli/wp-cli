<?php

namespace WP_CLI\Dispatcher;

class RootCommand extends AbstractCommandContainer implements Documentable {

	function get_name() {
		return 'wp';
	}

	function get_parent() {
		return false;
	}

	function get_shortdesc() {
		return '';
	}

	function get_full_synopsis() {
		return '';
	}

	function show_usage() {
		\WP_CLI::line( 'Available commands:' );

		foreach ( $this->get_subcommands() as $command ) {
			\WP_CLI::line( sprintf( "    %s %s",
				implode( ' ', get_path( $command ) ),
				implode( '|', array_keys( get_subcommands( $command ) ) )
			) );
		}

		\WP_CLI::line(<<<EOB

See 'wp help <command>' for more information on a specific command.

Global parameters:
--user=<id|login>   set the WordPress user
--url=<url>         set the URL
--path=<path>       set the path to the WP install
--config=<path>     set the path to the wp-cli config file
--require=<path>    load a certain PHP file before running the command
--quiet             suppress informational messages
--debug             show all PHP errors
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

	function get_subcommands() {
		$this->load_all_commands();

		return parent::get_subcommands();
	}

	protected function load_all_commands() {
		foreach ( glob( WP_CLI_ROOT . "/commands/*.php" ) as $filename ) {
			$command = str_replace( '.php', '', $filename );

			if ( isset( $this->subcommands[ $command ] ) )
				continue;

			include $filename;
		}
	}

	protected function load_command( $command ) {
		if ( !isset( $this->subcommands[ $command ] ) ) {
			if ( $path = \WP_CLI\Utils\get_command_file( $command ) )
				include $path;
		}

		if ( !isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}
}

