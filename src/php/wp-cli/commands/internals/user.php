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

		$user_id = WP_CLI::get_numeric_arg( $args, 0, "User ID" );

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

		if ( count( $args ) < 2 ) {
			WP_CLI::error( "Login and email required." );
		}

		list( $user_login, $user_email ) = $args;

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

		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $user_id );
		else
			WP_CLI::success( "Created user $user_id." );
	}

	/**
	 * Update a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function update( $args, $assoc_args ) {
		$user_id = WP_CLI::get_numeric_arg( $args, 0, "User ID" );

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
	 * Generate users
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function generate( $args, $assoc_args ) {
		global $blog_id;

		$defaults = array(
			'count' => 100,
			'role' => get_option('default_role'),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::warning( "invalid role." );
			exit;
		}

		$user_count = count_users();

		$total = $user_count['total_users'];

		$limit = $count + $total;

		$notify = new \cli\progress\Bar( 'Generating users', $count );

		for ( $i = $total; $i < $limit; $i++ ) {
			$login = sprintf( 'user_%d_%d', $blog_id, $i );
			$name = "User $i";

			$user_id = wp_insert_user( array(
				'user_login' => $login,
				'user_pass' => $login,
				'nickname' => $name,
				'display_name' => $name,
				'role' => $role
			) );

			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			$notify->tick();
		}

		$notify->finish();
	}

	/**
	 * Add a user to a blog
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function set_role( $args, $assoc_args ) {

		list( $id_or_login, $role ) = $args;

		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		if ( ! $user )
			WP_CLI::error( "Please specify a valid user ID or user login to add to this blog" );

		if ( ! get_role( $role ) )
			$role = get_option( 'default_role' );

		// Multisite
		if ( function_exists( 'add_user_to_blog' ) )
			add_user_to_blog( get_current_blog_id(), $user->ID, $role );
		else
			$user->set_role( $role );

		WP_CLI::success( "Added {$user->user_login} ({$user->ID}) to " . site_url() . " as {$role}" );
	}

	/**
	 * Remove a user from a blog
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function remove_role( $args, $assoc_args ) {

		list( $id_or_login ) = $args;

		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		if ( ! $user )
			WP_CLI::error( "Please specify a valid user ID or user login to remove from this blog" );

		// Multisite
		if ( function_exists( 'remove_user_from_blog' ) )
			remove_user_from_blog( $user->ID, get_current_blog_id() );
		else
			$user->remove_all_caps();

		WP_CLI::success( "Removed {$user->user_login} ({$user->ID}) from " . site_url() );
	}

}
