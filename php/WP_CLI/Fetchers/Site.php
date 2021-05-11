<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress site based on one of its attributes.
 */
class Site extends Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg = 'Could not find the site with ID %d.';

	/**
	 * Get a site object by ID
	 *
	 * @param int $site_id
	 * @return object|false
	 */
	public function get( $site_id ) {
		return $this->get_site( $site_id );
	}

	/**
	 * Get site (blog) data for a given id.
	 *
	 * @param string $arg The raw CLI argument.
	 * @return array|false The item if found; false otherwise.
	 */
	private function get_site( $arg ) {
		global $wpdb;

		// Load site data
		$site = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d",
				$arg
			)
		);

		if ( ! empty( $site ) ) {
			// Only care about domain and path which are set here
			return $site;
		}

		return false;
	}
}
