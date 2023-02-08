<?php

namespace WP_CLI\Fetchers;

use WP_CLI;
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

		if ( getenv( 'WP_CLI_FORCE_USER_LOGIN' ) ) {
			$this->msg = "Invalid user login: '%s'";
			return get_user_by( 'login', $arg );
		}

		if ( is_numeric( $arg ) ) {
			$check = get_user_by( 'login', $arg );
			$user  = get_user_by( 'id', $arg );
			if ( $check && $user ) {
				WP_CLI::warning(
					sprintf(
						'Ambiguous user match (ID=%d and user_login=%d). Defaulting to ID. Force user_login with WP_CLI_FORCE_USER_LOGIN=1.',
						$arg,
						$arg,
						$arg
					)
				);
			}
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

