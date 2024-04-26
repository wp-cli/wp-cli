<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a signup based on one of its attributes.
 */
class Signup extends Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg = "Invalid signup ID, email, login, or activation key: '%s'";

	/**
	 * Get a signup.
	 *
	 * @param int|string $signup
	 * @return stdClass|false
	 */
	public function get( $signup ) {
		return $this->get_signup( $signup );
	}

	/**
	 * Get a signup by one of its identifying attributes.
	 *
	 * @param string $arg The raw CLI argument.
	 * @return stdClass|false The item if found; false otherwise.
	 */
	protected function get_signup( $arg ) {
		global $wpdb;

		$signup_object = null;

		// Fetch signup with signup_id.
		if ( is_numeric( $arg ) ) {
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE signup_id = %d", $arg ) );

			if ( $result ) {
				$signup_object = $result;
			}
		}

		if ( ! $signup_object ) {
			// Try to fetch with other keys.
			foreach ( array( 'user_login', 'user_email', 'activation_key' ) as $field ) {
				// phpcs:ignore WordPress.DB.PreparedSQL
				$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE $field = %s", $arg ) );

				if ( $result ) {
					$signup_object = $result;
					break;
				}
			}
		}

		if ( $signup_object ) {
			return $signup_object;
		}

		return false;
	}
}
