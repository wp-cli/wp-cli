<?php

namespace WP_CLI\Fetchers;

use WP_User;

/**
 * Fetch a WordPress user based on one of its attributes.
 */
class User extends Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg = "Invalid user ID, email or login: '%s'";

	/**
	 * Get a user object by one of its identifying attributes.
	 *
	 * @param string $arg The raw CLI argument.
	 * @return WP_User|false The item if found; false otherwise.
	 */
	public function get( $arg ) {

		if ( is_numeric( $arg ) ) {
			$user = get_user_by( 'id', $arg );
		} elseif ( is_email( $arg ) ) {
			$user = get_user_by( 'email', $arg );
			// Logins can be emails.
			if ( ! $user ) {
				$user = get_user_by( 'login', $arg );
			}
		} else {
			$user = get_user_by( 'login', $arg );
		}

		return $user;
	}
}

