<?php

namespace WP_CLI;
use \WP_CLI;

/**
 * Class that handles special assoc parameters
 */
class InternalAssoc {

	static function version() {
		WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	}

	static function info() {
		$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : getenv( 'WP_CLI_PHP_USED' );

		WP_CLI::line( "PHP binary:\t" . $php_bin );
		WP_CLI::line( "PHP version:\t" . PHP_VERSION );
		WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
		WP_CLI::line( "wp-cli root:\t" . WP_CLI_ROOT );
		WP_CLI::line( "wp-cli config:\t" . WP_CLI::get_config_path() );
		WP_CLI::line( "wp-cli version:\t" . WP_CLI_VERSION );
	}

	static function param_dump() {
		echo json_encode( Utils\get_config_spec() );
	}

	static function cmd_dump() {
		echo json_encode( self::command_to_array( WP_CLI::$root ) );
	}

	private static function command_to_array( $command ) {
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

	static function completions() {
		foreach ( WP_CLI::$root->get_subcommands() as $name => $command ) {
			$subcommands = Dispatcher\get_subcommands( $command );

			WP_CLI::line( $name . ' ' . implode( ' ', array_keys( $subcommands ) ) );
		}
	}

	static function man( $args ) {
		if ( '' === exec( 'which ronn' ) ) {
			WP_CLI::error( '`ronn` executable not found.' );
		}

		$arg_copy = $args;

		$command = WP_CLI::$root;

		while ( !empty( $args ) && $command && $command instanceof Dispatcher\CommandContainer ) {
			$command = $command->find_subcommand( $args );
		}

		if ( !$command )
			WP_CLI::error( sprintf( "'%s' command not found.",
				implode( ' ', $arg_copy ) ) );

		foreach ( WP_CLI::get_man_dirs() as $dest_dir => $src_dir ) {
			WP_CLI\Man\generate( $src_dir, $dest_dir, $command );
		}
	}
}

