<?php

namespace WP_CLI\Fetchers;

class Site extends Base {

	protected $msg = "Could not find the site with ID %d.";

	public function get( $site_id ) {
		return $this->_get_site( $site_id );
	}
	
	/**
	 * Get site (blog) data for a given id.
	 *
	 * @param int     $site_id
	 * @return bool|array False if no site found with given id, array otherwise
	 */
	private function _get_site( $site_id ) {
		global $wpdb;

		// Load site data
		$site = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $wpdb->blogs WHERE blog_id = %d", $site_id ) );

		if ( !empty( $site ) ) {
			// Only care about domain and path which are set here
			return $site;
		}

		return false;
	}
}
