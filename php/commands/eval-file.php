<?php

class EvalFile_Command extends WP_CLI_Command {

	/**
	 * Load and execute a PHP file after loading WordPress.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The path to the PHP file to execute.
	 *
	 * [<arg>...]
	 * : One or more arguments to pass to the file.
	 */
	public function __invoke( $args ) {
		$file = array_shift( $args );

		if ( !file_exists( $file ) ) {
			WP_CLI::error( "'$file' does not exist." );
		}

		self::_eval( $file, $args );
	}

	private static function _eval( $file, $args ) {
		include( $file );
	}
}

WP_CLI::add_command( 'eval-file', 'EvalFile_Command' );

