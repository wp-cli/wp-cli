<?php

/**
 * Manage user roles.
 *
 * @package wp-cli
 */
class Role_Command extends WP_CLI_Command {

	/**
	 * List all roles.
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {
		global $wp_roles;

		$defaults = array(
			'format'    => 'table',
		);
		$params = array_merge( $defaults, $assoc_args );

		$fields = array(
				'name',
				'role',
			);

		$output_roles = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$output_role = new stdClass;

			$output_role->name = $role['name'];
			$output_role->role = $key;

			$output_roles[] = $output_role;
		}

		WP_CLI\Utils\format_items( $params['format'], $fields, $output_roles );
	}

	/**
	 * Check if a role exists.
	 * Will return 0 if the role exists, 1 if it does not.
	 *
	 * @synopsis <role-key>
	 */
	public function exists( $args ) {
		global $wp_roles;

		if ( ! in_array($args[0], array_keys( $wp_roles->roles ) ) ) {
			exit(1);
		}
	}

	/**
	 * Create a new role.
	 *
	 * @synopsis <role-key> <role-name>
	 */
	public function create( $args ) {
		self::persistence_check();

		$role_key = array_shift( $args );
		$role_name = array_shift( $args );

		if ( empty( $role_key ) || empty( $role_name ) )
			WP_CLI::error( "Can't create role, insufficient information provided.");

		if ( ! add_role( $role_key, $role_name ) )
			WP_CLI::error( "Role couldn't be created." );
		else
			WP_CLI::success( sprintf( "Role with key %s created.", $role_key ) );

	}

	/**
	 * Delete an existing role.
	 *
	 * @synopsis <role-key>
	 */
	public function delete( $args ) {

		global $wp_roles;

		self::persistence_check();

		$role_key = array_shift( $args );

		if ( empty( $role_key ) || ! isset( $wp_roles->roles[$role_key] ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		remove_role( $role_key );

		// Note: remove_role() doesn't indicate success or otherwise, so we have to
		// check ourselves
		if ( ! isset( $wp_roles->roles[$role_key] ) )
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

WP_CLI::add_command( 'role', 'Role_Command' );
