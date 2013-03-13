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
EOB
	);

		\WP_CLI::line( self::generate_synopsis() );
	}

	private static function generate_synopsis() {
		$max_len = 0;

		$lines = array();

		foreach ( \WP_CLI\Utils\get_config_spec() as $key => $details ) {
			if ( false === $details['runtime'] )
				continue;

			if ( isset( $details['deprecated'] ) )
				continue;

			$synopsis = ( true === $details['runtime'] )
				? "--[no-]$key"
				: "--$key" . $details['runtime'];

			$cur_len = strlen( $synopsis );

			if ( $max_len < $cur_len )
				$max_len = $cur_len;

			$lines[] = array( $synopsis, $details['desc'] );
		}

		foreach ( $lines as $line ) {
			list( $synopsis, $desc ) = $line;

			\WP_CLI::line( '    ' . str_pad( $synopsis, $max_len ) . '  ' . $desc );
		}
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
		$cmd_dir = WP_CLI_ROOT . "commands";

		$iterator = new \DirectoryIterator( $cmd_dir );

		foreach ( $iterator as $filename ) {
			if ( '.php' != substr( $filename, -4 ) )
				continue;

			$command = substr( $filename, 0, -4 );

			if ( isset( $this->subcommands[ $command ] ) )
				continue;

			include "$cmd_dir/$filename";
		}
	}

	protected static function get_command_file( $command ) {
		$path = WP_CLI_ROOT . "/commands/$command.php";

		if ( !is_readable( $path ) ) {
			return false;
		}

		return $path;
	}

	protected function load_command( $command ) {
		if ( !isset( $this->subcommands[ $command ] ) ) {
			if ( $path = self::get_command_file( $command ) )
				include $path;
		}

		if ( !isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}
}

