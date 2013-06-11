<?php

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 *
	 * @synopsis [--basic]
	 */
	public function __invoke( $_, $assoc_args ) {
		$implementations = array(
			'\\Boris\\Boris',
			'\\WP_CLI\\REPL',
		);

		if ( isset( $assoc_args['basic'] ) ) {
			unset( $implementations[0] );
		}

		foreach ( $implementations as $class ) {
			if ( class_exists( $class ) ) {
				$repl = new $class( 'wp> ' );
				$repl->start();
				break;
			}
		}
	}
}

\WP_CLI::add_command( 'shell', 'Shell_Command' );

