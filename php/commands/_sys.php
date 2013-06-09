<?php

use \WP_CLI\Dispatcher,
	\WP_CLI\Utils;

class Sys_Command extends WP_CLI_Command {

	private function command_to_array( $command ) {
		$dump = array(
			'name' => $command->get_name(),
			'description' => $command->get_shortdesc(),
		);

		if ( $command instanceof Dispatcher\AtomicCommand ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		} else {
			foreach ( Dispatcher\get_subcommands( $command ) as $subcommand ) {
				$dump['subcommands'][] = self::command_to_array( $subcommand );
			}
		}

		return $dump;
	}

	function version() {
		WP_CLI::line( 'WP-CLI ' . WP_CLI_VERSION );
	}

	function info() {
		$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : getenv( 'WP_CLI_PHP_USED' );

		WP_CLI::line( "PHP binary:\t" . $php_bin );
		WP_CLI::line( "PHP version:\t" . PHP_VERSION );
		WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
		WP_CLI::line( "WP-CLI root:\t" . WP_CLI_ROOT );
		WP_CLI::line( "WP-CLI config:\t" . WP_CLI::get_config_path() );
		WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
	}

	/**
	 * @subcommand param-dump
	 */
	function param_dump() {
		echo json_encode( Utils\get_config_spec() );
	}

	/**
	 * @subcommand cmd-dump
	 */
	function cmd_dump() {
		echo json_encode( self::command_to_array( WP_CLI::$root ) );
	}
}

WP_CLI::add_command( '_sys', 'Sys_Command' );

