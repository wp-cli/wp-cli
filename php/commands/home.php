<?php

class Home_Command extends WP_CLI_Command {

	/**
	 * Open the wp-cli homepage in your browser.
	 */
	function __invoke() {
		// Open the url for the wp-cli repository
		$open = \WP_CLI\Utils\open_url( 'https://github.com/wp-cli/wp-cli' );

		if ( is_wp_error( $open ) ) {
			WP_CLI::error( $open );
			return;
		}

		WP_CLI::success( 'The wp-cli homepage should be opening in your browser.' );
	}
}

WP_CLI::add_command( 'home', 'Home_Command' );
