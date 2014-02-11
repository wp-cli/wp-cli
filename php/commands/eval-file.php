<?php

class EvalFile_Command extends WP_CLI_Command {

	/**
	 * Load and execute a PHP file after loading WordPress.
	 *
	 * ## EXAMPLES
	 *
	 * [<file>]
	 * : The path to the PHP file to execute.
	 */
	public function __invoke( $args, $assoc_args ) {
		foreach ( $args as $file ) {
			if ( !file_exists( $file ) ) {
				WP_CLI::error( "'$file' does not exist." );
			} else {
				include( $file );
			}
		}
	}
}

WP_CLI::add_command( 'eval-file', 'EvalFile_Command' );

