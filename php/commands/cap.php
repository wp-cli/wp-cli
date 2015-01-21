<?php

/**
 * Manage user capabilities.
 *
 * ## EXAMPLES
 *
 *     # Add 'spectate' capability to 'author' role
 *     wp cap add 'author' 'spectate'
 *
 *     # Add all caps from 'editor' role to 'author' role
 *     wp cap list 'editor' | xargs wp cap add 'author'
 *
 *     # Remove all caps from 'editor' role that also appear in 'author' role
 *     wp cap list 'author' | xargs wp cap remove 'editor'
 */
class Capabilities_Command extends WP_CLI_Command {

	/**
	 * List capabilities for a given role.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display alphabetical list of bbPress moderator capabilities
	 *     wp cap list 'bbp_moderator' | sort
	 *
	 * @subcommand list
	 * @synopsis <role>
	 */
	public function list_( $args ) {
		$role_obj = self::get_role( $args[0] );

		foreach ( array_keys( $role_obj->capabilities ) as $cap )
			WP_CLI::line( $cap );
	}

	/**
	 * Add capabilities to a given role.
	 *
	 * @synopsis <role> <cap>...
	 */
	public function add( $args ) {
		self::persistence_check();

		$role = array_shift( $args );

		$role_obj = self::get_role( $role );

		$count = 0;

		foreach ( $args as $cap ) {
			if ( $role_obj->has_cap( $cap ) )
				continue;

			$role_obj->add_cap( $cap );

			$count++;
		}

		WP_CLI::success( sprintf( "Added %d capabilities to '%s' role." , $count, $role ) );
	}

	/**
	 * Remove capabilities from a given role.
	 *
	 * @synopsis <role> <cap>...
	 */
	public function remove( $args ) {
		self::persistence_check();

		$role = array_shift( $args );

		$role_obj = self::get_role( $role );

		$count = 0;

		foreach ( $args as $cap ) {
			if ( !$role_obj->has_cap( $cap ) )
				continue;

			$role_obj->remove_cap( $cap );

			$count++;
		}

		WP_CLI::success( sprintf( "Removed %d capabilities from '%s' role." , $count, $role ) );
	}

	private static function get_role( $role ) {
		global $wp_roles;

		$role_obj = $wp_roles->get_role( $role );

		if ( !$role_obj )
			WP_CLI::error( "'$role' role not found." );

		return $role_obj;
	}

	private static function persistence_check() {
		global $wp_roles;

		if ( !$wp_roles->use_db )
			WP_CLI::error( "Role definitions are not persistent." );
	}
}

WP_CLI::add_command( 'cap', 'Capabilities_Command' );

