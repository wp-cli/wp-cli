<?php

class Eval_Command extends WP_CLI_Command {

	/**
	 * Execute arbitrary PHP code after loading WordPress.
	 *
	 * <php-code>
	 * : The code to execute, as a string.
	 *
	 * ## EXAMPLES
	 *
	 *     wp eval 'echo WP_CONTENT_DIR;'
	 */
	public function __invoke( $args, $assoc_args ) {
		eval( $args[0] );
	}
}

WP_CLI::add_command( 'eval', 'Eval_Command' );

