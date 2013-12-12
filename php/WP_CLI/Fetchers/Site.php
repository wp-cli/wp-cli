<?php

namespace WP_CLI\Fetchers;

class Site extends Base {

	protected $msg = "Could not find the site with ID %d.";

	public function get( $site_id ) {
		return $this->_get_site( $site_id );
	}
	
	/**
	 * Get site (network) data for a given id.
	 *
	 * @param int     $site_id
	 * @return bool|array False if no network found with given id, array otherwise
	 */
	private function _get_site( $site_id ) {
		global $wpdb;

		// Load site data
		$sites = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->site WHERE id = %d", $site_id ) );

		if ( !empty( $sites ) ) {
			// Only care about domain and path which are set here
			return $sites[0];
		}

		return false;
	}
}
