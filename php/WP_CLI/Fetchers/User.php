<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress user based on one of its attributes.
 */
class User extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "Invalid user ID, email or login: '%s'";

	/**
	 * Get a user object by one of its identifying attributes
	 * 
	 * @param mixed $id_email_or_login
	 * @return WP_User|false
	 */
	public function get( $id_email_or_login ) {

		if ( is_numeric( $id_email_or_login ) )
			$user = get_user_by( 'id', $id_email_or_login );
		else if ( is_email( $id_email_or_login ) )
			$user = get_user_by( 'email', $id_email_or_login );
		else
			$user = get_user_by( 'login', $id_email_or_login );

		return $user;
	}
}

