<?php

namespace WP_CLI\Fetchers;

class User extends Base {

	protected $msg = "Invalid user ID or login: '%s'";

	public function get( $id_or_login ) {
		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		return $user;
	}
}

