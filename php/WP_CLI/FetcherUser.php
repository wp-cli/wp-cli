<?php

namespace WP_CLI;

class FetcherUser implements Fetcher {

	public function get( $id_or_login ) {
		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		return $user;
	}

	public function get_check( $id_or_login ) {
		$user = $this->get( $id_or_login );

		if ( ! $user ) {
			\WP_CLI::error( "Invalid user ID or login: $id_or_login" );
		}

		return $user;
	}

	public function get_many( $ids_or_logins ) {
		$users = array();

		foreach ( $ids_or_logins as $id_or_login ) {
			$user = $this->get( $id_or_login );
			if ( $user ) {
				$users[] = $user;
			} else {
				\WP_CLI::warning( "Invalid user ID or login: $id_or_login" );
			}
		}

		return $users;
	}
}

