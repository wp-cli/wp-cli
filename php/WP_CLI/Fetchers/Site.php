<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress site based on one of its attributes.
 *
 * @phpstan-type SiteObject object{blog_id: int, site_id: int, domain: string, path: string, registered: string, last_updated: string, public: int, archived: int, mature: int, spam: int, deleted: int, lang_id: int}
 *
 * @extends Base<SiteObject>
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
	 * @param string|int $site_id
	 * @return object|false
	 *
	 * @phpstan-return SiteObject|false
	 */
	public function get( $site_id ) {
		return $this->get_site( (int) $site_id );
	}

	/**
	 * Get site (blog) data for a given id.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string|int $arg The raw CLI argument.
	 * @return object|false The item if found; false otherwise.
	 *
	 * @phpstan-return SiteObject|false
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
