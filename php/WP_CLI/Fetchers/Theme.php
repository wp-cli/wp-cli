<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress theme based on one of its attributes.
 */
class Theme extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "The '%s' theme could not be found.";

	/**
	 * Get a theme object by name
	 * 
	 * @param string $name
	 * @return object|false
	 */
	public function get( $name ) {
		$theme = wp_get_theme( $name );

		if ( !$theme->exists() ) {
			return false;
		}

		return $theme;
	}
}

