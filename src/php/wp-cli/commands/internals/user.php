<?php

WP_CLI::add_command('user', 'User_Command');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Command extends WP_CLI_Command {

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
		
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");

		$defaults = array( 'reassign' => NULL );

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( wp_delete_user( $user_id, $reassign ) ) {
			WP_CLI::success( "Deleted user $user_id." );
		} else {
			WP_CLI::error( "Failed deleting user $user_id." );
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
			self::error_see_help( "Login and email required" );
		}

		$defaults = array(
			'role' => get_option('default_role'),
			'user_pass' => wp_generate_password(),
			'user_registered' => strftime( "%F %T", time() ),
			'display_name' => false,
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::error( "Invalid role." );
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
			WP_CLI::error( $user_id );
		} else {
			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}
		}

		WP_CLI::success( "Created user $user_id." );
	}

	/**
	 * Update a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function update( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "Need some fields to update." );
		}

		$params = array_merge( array( 'ID' => $user_id ), $assoc_args );

		$updated_id = wp_update_user( $params );

		if ( is_wp_error( $updated_id ) ) {
			WP_CLI::error( $updated_id );
		} else {
			WP_CLI::success( "Updated user $updated_id." );
		}
	}

	/**
	 * Update meta field for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function update_meta( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");;
		$meta_value = self::get_arg_or_error($args, 2, "meta_value");;

		$success = update_user_meta( $user_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Updated user $user_id." );
		} else {
			WP_CLI::error( "Failed to update meta field" );
		}
	}
	
	private function get_numeric_arg_or_error( $args, $index, $name ) {
		$value = self::get_arg_or_error( $args, $index, $name );
		if ( ! is_numeric( $value ) ) {
			self::error_see_help( "$name must be numeric" );
		}
		return $value;
	}
	
	private function get_arg_or_error( $args, $index, $name ) {
		if ( ! isset( $args[$index] ) ) {
			self::error_see_help( "$name required" );
		}
		return $args[$index];
	}
	
	private function error_see_help( $message ) {
		WP_CLI::error( "$message (see 'wp user help').");
	}
}
