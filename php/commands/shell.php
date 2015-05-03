<?php

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 *
	 * ## DESCRIPTION
	 *
	 * `wp shell` allows you to evaluate PHP statements and expressions interactively, from within a WordPress environment. This means that you have access to all the functions, classes and globals that you would have access to from inside a WordPress plugin, for example.
	 *
	 * ## OPTIONS
	 *
	 * [--basic]
	 * : Start in fail-safe mode, even if Boris is available.
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
			$repl = new $class( 'wp> ' );
			$repl->start();
		}
	}
}

\WP_CLI::add_command( 'shell', 'Shell_Command' );

