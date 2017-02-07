<?php

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 *
	 * `wp shell` allows you to evaluate PHP statements and expressions
	 * interactively, from within a WordPress environment. Type a bit of code,
	 * hit enter, and see the code execute right before you. Because WordPress
	 * is loaded, you have access to all the functions, classes and globals
	 * that you can use within a WordPress plugin, for example.
	 *
	 * ## OPTIONS
	 *
	 * [--basic]
	 * : Start in fail-safe mode, even if Boris is available.
	 *
	 * ## EXAMPLES
	 *
	 *     # Call get_bloginfo() to get the name of the site.
	 *     $ wp shell
	 *     wp> get_bloginfo( 'name' );
	 *     => string(6) "WP-CLI"
	 */
	public function __invoke( $_, $assoc_args ) {
		$implementations = array(
			'\\Psy\\Shell',
			'\\Boris\\Boris',
			'\\WP_CLI\\REPL',
		);

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'basic' ) ) {
			$class = '\\WP_CLI\\REPL';
		} else {
			foreach ( $implementations as $candidate ) {
				if ( class_exists( $candidate ) ) {
					$class = $candidate;
					break;
				}
			}
		}

		if ( '\\Psy\\Shell' == $class ) {
			\Psy\Shell::debug();
		} else {
			$repl = new $class(  "wp> " );
			$repl->start();
		}
	}
}

\WP_CLI::add_command( 'shell', 'Shell_Command' );

