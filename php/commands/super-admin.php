<?php

class Super_Admin_Command extends WP_CLI_Command {

	/**
	 * Show a list of users with super-admin capabilities.
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$super_admins = self::get_admins();
		foreach ( $super_admins as $user_login ) {
			WP_CLI::line( $user_login );
		}
	}

	/**
	 * Grant super-admin privileges to one or more users.
	 *
	 * <user>...
	 * : One or more user logins.
	 */
	public function add( $args, $_ ) {
		$super_admins = self::get_admins();

		foreach ( $args as $user_login ) {
			$user = get_user_by( 'login', $user_login );
			if ( !$user ) {
				WP_CLI::warning( "Couldn't find {$user_login} user." );
			} else {
				$super_admins[] = $user->user_login;
			}
		}

		update_site_option( 'site_admins' , $super_admins );
		WP_CLI::success( 'Granted super-admin capabilities.' );
	}

	/**
	 * Revoke super-admin privileges to one or more users.
	 *
	 * <user>...
	 * : One or more user logins.
	 */
	public function remove( $args, $_ ) {
		$super_admins = self::get_admins();
		$super_admins = array_diff( $super_admins, $args );
		update_site_option( 'site_admins' , $super_admins );
		WP_CLI::success( 'Revoked super-admin capabilities.' );
	}

	private static function get_admins() {
		// We don't use get_super_admins() because we don't want to mess with the global
		return get_site_option( 'site_admins', array('admin') );
	}
}

WP_CLI::add_command( 'super-admin', 'Super_Admin_Command' );

