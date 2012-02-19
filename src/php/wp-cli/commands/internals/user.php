<?php

WP_CLI::addCommand('user', 'UserCommand');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class UserCommand extends WP_CLI_Command {

	/**
	 * List users
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function _list( $args, $assoc_args ) {
		global $blog_id;

		$params = array(
			'blog_id' => $blog_id,
			'fields' => 'all_with_meta',
		);

		if ( array_key_exists('role', $assoc_args) ) {
			$params['role'] = $assoc_args['role'];
		}

		$table = new \cli\Table();
		$users = get_users( $params );
		$fields = array('ID', 'user_login', 'display_name', 'user_email',
			'user_registered');

		$table->setHeaders( array_merge($fields, array('roles')) );

		foreach ( $users as $user ) {
			$line = array();

			foreach ( $fields as $field ) {
				$line[] = $user->$field;
			}
			$line[] = implode( ',', $user->roles );

			$table->addRow($line);
		}

		$table->display();

		WP_CLI::line( 'Total: ' . count($users) . ' users' );
	}

	/**
	 * Delete a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function delete( $args, $assoc_args ) {
		global $blog_id;

		$user_id = $args[0];

		if ( ! is_numeric($user_id) ) {
			WP_CLI::error("User ID required (see 'wp user help')");
		}

		$defaults = array( 'reassign' => NULL );

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( wp_delete_user( $user_id, $reassign ) ) {
			WP_CLI::line( "Deleted user $user_id" );
		} else {
			WP_CLI::error( "Failed deleting user $user_id" );
		}
	}

	/**
	 * Create a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function create( $args, $assoc_args ) {
		global $blog_id;

		$user_login = $args[0];
		$user_email = $args[1];

		if ( ! $user_login || ! $user_email ) {
			WP_CLI::error("Login and email required (see 'wp user help').");
		}

		$defaults = array(
			'role' => get_option('default_role'),
			'user_pass' => wp_generate_password(),
			'user_registered' => strftime( "%F %T", time() ),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::warning( "invalid role." );
			exit;
		}

		$user_id = wp_insert_user( array(
			'user_email' => $user_email,
			'user_login' => $user_login,
			'user_pass' => $user_pass,
			'user_registered' => $user_registered,
			'display_name' => $display_name,
			'role' => $role,
		) );

		if ( is_wp_error($user_id) ) {
			WP_CLI::error( $user_id->get_error_message() );
		} else {
			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}
		}

		WP_CLI::line( "Created user $user_id" );
	}

	/**
	 * Update a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function update( $args, $assoc_args ) {
		$user_id = $args[0];

		if ( ! is_numeric($user_id) ) {
			WP_CLI::error( "User ID required (see 'wp user help')" );
		}

		if ( ! count($assoc_args) ) {
			WP_CLI::error( "Need some fields to update" );
		}

		$params = array_merge( array('ID' => $user_id), $assoc_args );

		$updated_id = wp_update_user( $params );

		if ( is_wp_error($updated_id) ) {
			WP_CLI::error( $updated_id->get_error_message() );
		} else {
			WP_CLI::line( "Updated user $updated_id" );
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp user list [--role=<role>]
   or: wp user create <user_login> <user_email> [--role=<default_role>]
   or: wp user update <ID> [--field_name=<field_value>]
   or: wp user delete <ID> [--reassign=<reassign_id>]
EOB
	);
	}
}
