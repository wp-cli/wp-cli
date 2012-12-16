<?php

WP_CLI::add_command( 'cap', 'Capabilities_Command' );

/**
 * Manage capabilities.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Capabilities_Command extends WP_CLI_Command {

	/**
	 * List capabilities for a given role.
	 *
	 * @subcommand list
	 * @synopsis <role>
	 */
	public function _list( $args ) {
		global $wp_roles;

		list( $role ) = $args;

		$role_obj = $wp_roles->get_role( $role );

		if ( !$role_obj )
			WP_CLI::error( "$role does not exist" );

		foreach ( array_keys( $role_obj->capabilities ) as $cap )
			WP_CLI::line( $cap );
	}
}

