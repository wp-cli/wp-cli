<?php

namespace WP_CLI;

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 */
class UpgraderSkin extends \WP_Upgrader_Skin {

	function header() {}
	function footer() {}
	function bulk_header() {}
	function bulk_footer() {}

	function error( $error ) {
		if ( !$error )
			return;

		if ( is_string( $error ) && isset( $this->upgrader->strings[ $error ] ) )
			$error = $this->upgrader->strings[ $error ];

		// TODO: show all errors, not just the first one
		\WP_CLI::warning( $error );
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

		\WP_CLI::line( $string );
	}
}

