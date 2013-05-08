<?php

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 */
	public function __invoke() {
		\WP_CLI::line( 'Type "exit" to close session.' );

		$implementations = array(
			'\\Boris\\Boris',
			'\\WP_CLI\\REPL',
		);

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

