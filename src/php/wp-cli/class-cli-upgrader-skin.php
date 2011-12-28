<?php

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 */
class CLI_Upgrader_Skin extends WP_Upgrader_Skin {

	function header() {}
	function footer() {}

	// TODO: show prompt
	function request_filesystem_credentials( $error = false ) {
		$this->error( $error );
	}

	function error( $error ) {
		if ( !$error )
			return;

		// TODO: show all errors, not just the first one
		WP_CLI::warning( WP_CLI::errorToString( $error ) );
	}

	function feedback( $string ) {
		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( !empty($args) )
				$string = vsprintf($string, $args);
		}

		if ( empty($string) )
			return;

		$string = str_replace( '&#8230;', '...', strip_tags( $string ) );

		WP_CLI::line( $string );
	}
}

