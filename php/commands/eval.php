<?php

class Eval_Command extends WP_CLI_Command {

	/**
	 * Execute arbitrary PHP code.
	 *
	 * <php-code>
	 * : The code to execute, as a string.
	 *
	 * [--skip-wordpress]
	 * : Execute code without loading WordPress.
	 *
	 * @when before_wp_load
	 *
	 * ## EXAMPLES
	 *
	 *     wp eval 'echo WP_CONTENT_DIR;'
	 */
	public function __invoke( $args, $assoc_args ) {

		if ( null === \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-wordpress' ) ) {
			WP_CLI::get_runner()->load_wordpress();
		}

		eval( $args[0] );
	}
}

WP_CLI::add_command( 'eval', 'Eval_Command' );

