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
	 * List users.
	 *
	 * @subcommand list
	 * @synopsis [--role=<role>] [--ids]
	 */
	public function _list( $args, $assoc_args ) {
		global $blog_id;

		$params = array(
			'blog_id' => $blog_id,
			'fields' => isset( $assoc_args['ids'] ) ? 'ids' : 'all_with_meta',
		);

		if ( array_key_exists('role', $assoc_args) ) {
			$params['role'] = $assoc_args['role'];
		}

		$users = get_users( $params );

		if ( isset( $assoc_args['ids'] ) ) {
			WP_CLI::out( implode( ' ', $users ) );
			return;
		}

		$fields = array('ID', 'user_login', 'display_name', 'user_email',
			'user_registered');

		$table = new \cli\Table();

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
	 * Delete a user.
	 *
	 * @synopsis <id> [--reassign=<id>]
	 */
	public function delete( $args, $assoc_args ) {
		global $blog_id;

		list( $user_id ) = $args;

		$defaults = array( 'reassign' => NULL );

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( wp_delete_user( $user_id, $reassign ) ) {
			WP_CLI::success( "Deleted user $user_id." );
		} else {
			WP_CLI::error( "Failed deleting user $user_id." );
		}
	}

	/**
	 * Create a user.
	 *
	 * @synopsis <user-login> <user-email> [--role=<role>] [--user_pass=<password>] [--user_registered=<yyyy-mm-dd>] [--display_name=<name>] [--porcelain]
	 */
	public function create( $args, $assoc_args ) {
		global $blog_id;

		list( $user_login, $user_email ) = $args;

		$defaults = array(
			'role' => get_option('default_role'),
			'user_pass' => false,
			'user_registered' => strftime( "%F %T", time() ),
			'display_name' => false,
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::error( "Invalid role." );
		}

		if ( !$user_pass ) {
			$user_pass = wp_generate_password();
			$generated_pass = true;
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

		if ( isset( $assoc_args['porcelain'] ) ) {
			WP_CLI::line( $user_id );
		} else {
			WP_CLI::success( "Created user $user_id." );
			if ( isset( $generated_pass ) )
				WP_CLI::line( "Password: $user_pass" );
		}
	}

	/**
	 * Update a user.
	 *
	 * @synopsis <id> --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {
		list( $user_id ) = $args;

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
	 * Generate users.
	 *
	 * @synopsis [--count=100] [--role=<role>]
	 */
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
	 * Add a user to a blog.
	 *
	 * @subcommand set-role
	 * @synopsis <user-login> [<role>] [--blog=<blog>]
	 */
	public function set_role( $args, $assoc_args ) {
		$user = self::get_user_from_first_arg( $args[0] );

		$role = isset( $args[1] ) ? $args[1] : get_option( 'default_role' );

		// Multisite
		if ( function_exists( 'add_user_to_blog' ) )
			add_user_to_blog( get_current_blog_id(), $user->ID, $role );
		else
			$user->set_role( $role );

		WP_CLI::success( "Added {$user->user_login} ({$user->ID}) to " . site_url() . " as {$role}" );
	}

	/**
	 * Remove a user from a blog.
	 *
	 * @subcommand remove-role
	 * @synopsis <user-login>
	 */
	public function remove_role( $args, $assoc_args ) {
		$user = self::get_user_from_first_arg( $args[0] );

		// Multisite
		if ( function_exists( 'remove_user_from_blog' ) )
			remove_user_from_blog( $user->ID, get_current_blog_id() );
		else
			$user->remove_all_caps();

		WP_CLI::success( "Removed {$user->user_login} ({$user->ID}) from " . site_url() );
	}

	private static function get_user_from_first_arg( $id_or_login ) {
		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		if ( ! $user )
			WP_CLI::error( "Please specify a valid user ID or user login to remove from this blog" );

		return $user;
	}

	/**
	 * Import users from a CSV file.
	 *
	 * @subcommand import-csv
	 * @synopsis <file>
	 */
	public function import_csv( $args, $assoc_args ) {

		list( $csv ) = $args;

		$new_users = \WP_CLI\utils\parse_csv( $csv );

		$blog_users = get_users();

		foreach( $new_users as $new_user ) {

			$defaults = array(
				'role' => get_option('default_role'),
				'user_pass' => wp_generate_password(),
				'user_registered' => strftime( "%F %T", time() ),
				'display_name' => false,
			);
			$new_user = array_merge( $defaults, $new_user );

			if ( 'none' == $new_user['role'] ) {
				$new_user['role'] = false;
			} elseif ( is_null( get_role( $new_user['role'] ) ) ) {
				WP_CLI::warning( "{$new_user['user_login']} has an invalid role" );
				continue;
			}

			// User already exists and we just need to add them to the site if they aren't already there
			if ( $existing_user = get_user_by( 'email', $new_user['user_email'] ) ) {
				if ( in_array( $existing_user->user_login, wp_list_pluck( $blog_users, 'user_login' ) ) )
					WP_CLI::warning( "{$existing_user->user_login} already is a member of blog" );
				else if ( $new_user['role'] ) {
					add_user_to_blog( get_current_blog_id(), $existing_user->ID, $new_user['role'] );
					WP_CLI::line( "{$existing_user->user_login} added to blog as {$new_user['role']}" );
				} else {
					WP_CLI::line( "{$existing_user->user_login} exists, but won't be added to the blog" );
				}
				continue;
			}

			$user_id = wp_insert_user( $new_user );

			if ( is_wp_error( $user_id ) ) {
				WP_CLI::warning( $user_id );
				continue;
			} else {
				if ( false === $new_user['role'] ) {
					delete_user_option( $user_id, 'capabilities' );
					delete_user_option( $user_id, 'user_level' );
				}
			}

			WP_CLI::line( $new_user['user_login'] . " created" );
		}
	}
}

