<?php

class Eval_Command extends WP_CLI_Command {

	/**
	 * Execute arbitrary PHP code.
	 *
	 * ## OPTIONS
	 *
	 * <php-code>
	 * : The code to execute, as a string.
	 *
	 * [--skip-wordpress]
	 * : Execute code without loading WordPress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display WordPress content directory.
	 *     $ wp eval 'echo WP_CONTENT_DIR;'
	 *     /var/www/wordpress/wp-content
	 *
	 *     # Generate a random number.
	 *     $ wp eval 'echo rand();' --skip-wordpress
	 *     479620423
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {

		if ( null === \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-wordpress' ) ) {
			WP_CLI::get_runner()->load_wordpress();
		}

		eval( $args[0] );
	}
}

WP_CLI::add_command( 'eval', 'Eval_Command' );
