<?php

/**
 * Manage user roles.
 *
 * @package wp-cli
 */
class Roles_Command extends WP_CLI_Command {

	/**
	 * List one or all roles.
	 *
	 * @subcommand list
	 * @synopsis [<role-key>]
	 */
	public function _list( $args ) {
		global $wp_roles;

		if ( isset ( $args[0] ) )
			$target_role = $args[0];
		else
			$target_role = '';

		foreach ( $wp_roles->roles as $key => $role ) {
			if ( empty ( $target_role ) || $key == $target_role )
				WP_CLI::line( $role['name'] . " ($key)");
		}
	}

	/**
	 * Create a new role.
	 *
	 * @subcommand create
	 * @synopsis <role-key> <role-name>
	 */
	public function _create( $args ) {
		self::persistence_check();

		$role_key = array_shift( $args );
		$role_name = array_shift( $args );

		if ( empty ( $role_key ) || empty ( $role_name ) )
			WP_CLI::error( "Can't create role, insufficient information provided.");

		if ( ! add_role ( $role_key, $role_name ) )
			WP_CLI::error( "Role couldn't be created." );
		else
			WP_CLI::success( sprintf( "Role with key %s created.", $role_key ) );

	}

	/**
	 * Delete an existing role
	 *
	 * @subcommand delete
	 * @synopsis <role-key>
	 */
	public function _delete( $args ) {

		global $wp_roles;

		self::persistence_check();

		$role_key = array_shift( $args );

		if ( empty ( $role_key ) || ! isset ( $wp_roles->roles[$role_key] ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		remove_role ( $role_key );

		// Note: remove_role() doesn't indicate success or otherwise, so we have to
		// check ourselves
		if ( ! isset ( $wp_roles->roles[$role_key] ) )
			WP_CLI::success( sprintf( "Role with key %s deleted.", $role_key ) );
		else
			WP_CLI::error( sprintf( "Role with key %s could not be deleted.", $role_key ) );

	}

	private static function persistence_check() {
		global $wp_roles;

		if ( !$wp_roles->use_db )
			WP_CLI::error( "Role definitions are not persistent." );
	}
}

WP_CLI::add_command( 'roles', 'Roles_Command' );