<?php

/**
 * List, add, and remove super admins from a network.
 *
 * ## EXAMPLES
 *
 *     # List user with super-admin capabilities
 *     $ wp super-admin list
 *     supervisor
 *     administrator
 *
 *     # Grant super-admin privileges to the user.
 *     $ wp super-admin add superadmin2
 *     Success: Granted super-admin capabilities.
 *
 *     # Revoke super-admin privileges to the user.
 *     $ wp super-admin remove superadmin2
 *     Success: Revoked super-admin capabilities.
 *
 * @package wp-cli
 */
class Super_Admin_Command extends WP_CLI_Command {

	private $fields = array(
		'user_login'
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\User;
	}

	/**
	 * Show a list of users with super-admin capabilities.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List user with super-admin capabilities
	 *     $ wp super-admin list
	 *     supervisor
	 *     administrator
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$super_admins = self::get_admins();

		if ( 'list' === $assoc_args['format'] ) {
			foreach ( $super_admins as $user_login ) {
				WP_CLI::line( $user_login );
			}
		}
		else {
			$output_users = array();
			foreach ( $super_admins as $user_login ) {
				$output_user = new stdClass;

				$output_user->user_login = $user_login;

				$output_users[] = $output_user;
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
			$formatter->display_items( $output_users );
		}
	}

	/**
	 * Grant super-admin privileges to one or more users.
	 *
	 * ## OPTIONS
	 *
	 * <user>...
	 * : One or more user IDs, user emails, or user logins.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp super-admin add superadmin2
	 *     Success: Granted super-admin capabilities.
	 */
	public function add( $args, $_ ) {

		$users = $this->fetcher->get_many( $args );
		$user_logins = wp_list_pluck( $users, 'user_login' );
		$super_admins = self::get_admins();
		$num_super_admins = count( $super_admins );

		foreach ( $user_logins as $user_login ) {
			$user = get_user_by( 'login', $user_login );

			if ( !$user ) {
				WP_CLI::warning( "Couldn't find '{$user_login}' user." );
				continue;
			}

			if ( in_array( $user->user_login, $super_admins ) ) {
				WP_CLI::warning( "User '{$user_login}' already has super-admin capabilities." );
				continue;
			}

			$super_admins[] = $user->user_login;
		}

		if ( $num_super_admins === count( $super_admins ) ) {
			WP_CLI::log( 'No changes.' );
		} else {
			if ( update_site_option( 'site_admins' , $super_admins ) ) {
				WP_CLI::success( 'Granted super-admin capabilities.' );
			} else {
				WP_CLI::error( 'Site options update failed.' );
			}
		}
	}

	/**
	 * Revoke super-admin privileges to one or more users.
	 *
	 * ## OPTIONS
	 *
	 * <user>...
	 * : One or more user IDs, user emails, or user logins.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp super-admin remove superadmin2
	 *     Success: Revoked super-admin capabilities.
	 */
	public function remove( $args, $_ ) {
		$users = $this->fetcher->get_many( $args );
		$user_logins = wp_list_pluck( $users, 'user_login' );

		$super_admins = self::get_admins();
		$super_admins = array_diff( $super_admins, $user_logins );
		update_site_option( 'site_admins' , $super_admins );
		WP_CLI::success( 'Revoked super-admin capabilities.' );
	}

	private static function get_admins() {
		// We don't use get_super_admins() because we don't want to mess with the global
		return get_site_option( 'site_admins', array('admin') );
	}
}

WP_CLI::add_command( 'super-admin', 'Super_Admin_Command', array(
	'before_invoke' => function () {
		if ( !is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}
	}
) );
