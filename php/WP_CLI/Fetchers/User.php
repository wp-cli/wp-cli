<?php

namespace WP_CLI\Fetchers;

class User extends Base {

	protected $msg = "Invalid user ID, email or login: '%s'";

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

