<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress plugin based on one of its attributes.
 */
class Plugin extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "The '%s' plugin could not be found.";

	/**
	 * Get a plugin object by name
	 * 
	 * @param string $name
	 * @return object|false
	 */
	public function get( $name ) {
		foreach ( get_plugins() as $file => $_ ) {
			if ( $file === "$name.php" ||
				( $name && $file === $name ) ||
				( dirname( $file ) === $name && $name !== '.' ) ) {
				return (object) compact( 'name', 'file' );
			}
		}

		return false;
	}
}

