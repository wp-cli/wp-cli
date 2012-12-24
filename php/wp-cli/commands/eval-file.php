<?php

WP_CLI::add_command( 'eval-file', new EvalFile_Command );

class EvalFile_Command extends WP_CLI_Command {

	/**
	 * Loads and executes a PHP file after loading WordPress.
	 *
	 * @synopsis <path>
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

