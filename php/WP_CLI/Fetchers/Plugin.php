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

		$active_plugins = (array) get_option('active_plugins');
		$network_plugins = (array) get_site_option('active_sitewide_plugins');
		$all_plugins = array_unique( array_merge( array_values( $active_plugins ), array_keys( $network_plugins ) ) );

		foreach ( $all_plugins as $plugin ) {
			if ( dirname( $plugin ) === $name && $name !== '.' ) {
				return (object) array('name' => $plugin, 'file' => $plugin);
			}
		}

		return false;
	}
}

