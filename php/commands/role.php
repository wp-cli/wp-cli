<?php

/**
 * Manage user roles.
 *
 * @package wp-cli
 */
class Role_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'role'
	);

	/**
	 * List all roles.
	 *
	 * ## OPTIONS
	 *
	 * --fields=<fields>
	 * : Limit the output to specific object fields. Defaults to name,role.
	 *
	 * --format=<format>
	 * : Output list as table, CSV or JSON. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role list --fields=role --format=csv
	 *
	 * @subcommand list
	 * @synopsis [--fields=<fields>] [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {
		global $wp_roles;

		$defaults = array(
			'fields'    => implode( ',', $this->fields ),
			'format'    => 'table',
		);
		$params = array_merge( $defaults, $assoc_args );

		$fields = $params['fields'];
		unset( $params['fields'] );

		$output_roles = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$output_role = new stdClass;

			$output_role->name = $role['name'];
			$output_role->role = $key;

			$output_roles[] = $output_role;
		}

		WP_CLI\Utils\format_items( $params['format'], $output_roles, $fields );
	}

	/**
	 * Check if a role exists.
	 *
	 * ##DESCRIPTION
	 *
	 * Will exit with status 0 if the role exists, 1 if it does not.
	 *
	 * ## OPTIONS
	 *
	 * * <role-key>:
	 *
	 *     The internal name of the role, e.g. editor
	 *
	 * ## EXAMPLES
	 *
	 *     wp role exists editor
	 *
	 * @synopsis <role-key>
	 */
	public function exists( $args ) {
		global $wp_roles;

		if ( ! in_array($args[0], array_keys( $wp_roles->roles ) ) ) {
			WP_CLI::error( "Role with ID $args[0] does not exist." );
		}
		
		WP_CLI::success( "Role with ID $args[0] exists." );
	}

	/**
	 * Create a new role.
	 *
	 * ## OPTIONS
	 *
	 * * <role-key>:
	 *
	 *     The internal name of the role, e.g. editor
	 *
	 * * <role-name>:
	 *
	 *     The publically visible name of the role, e.g. Editor
	 *
	 * ## EXAMPLES
	 *
	 *     wp role create approver Approver
	 *
	 *     wp role create productadmin "Product Administrator"
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
	 * ## OPTIONS
	 *
	 * * <role-key>:
	 *
	 *     The internal name of the role, e.g. editor
	 *
	 * ## EXAMPLES
	 *
	 *     wp role delete approver
	 *
	 *     wp role delete productadmin
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
