<?php

/**
 * Manage users.
 *
 * @package wp-cli
 */
class User_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'user';

	private $fields = array(
		'ID',
		'user_login',
		'display_name',
		'user_email',
		'user_registered',
		'roles'
	);

	/**
	 * List users.
	 *
	 * @subcommand list
	 * @synopsis [--role=<role>] [--fields=<fields>] [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$defaults = array(
			'blog_id'   => get_current_blog_id(),
			'fields'    => implode( ',', $this->fields ),
			'format'    => 'table',
		);
		$params = array_merge( $defaults, $assoc_args );

		$fields = $params['fields'];
		unset( $params['fields'] );

		if ( array_key_exists( 'role', $assoc_args ) ) {
			$params['role'] = $assoc_args['role'];
		}

		if ( 'ids' == $params['format'] )
			$params['fields'] = 'ids';
		else
			$params['fields'] = 'all_with_meta';

		$users = get_users( $params );

		if ( 'ids' != $params['format'] ) {
			foreach ( $users as $user ) {
				$user->roles = implode( ',', $user->roles );
			}
		}

		WP_CLI\Utils\format_items( $params['format'], $users, $fields );
	}

	/**
	 * Delete one or more users.
	 *
	 * @synopsis <id>... [--reassign=<id>]
	 */
	public function delete( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'reassign' => null
		) );

		parent::delete( $args, $assoc_args );
	}

	protected function _delete( $user_id, $assoc_args ) {
		if ( is_multisite() ) {
			$r = wpmu_delete_user( $user_id );
		} else {
			$r = wp_delete_user( $user_id, $assoc_args['reassign'] );
		}

		if ( $r ) {
			return array( 'success', "Deleted user $user_id." );
		} else {
			return array( 'error', "Failed deleting user $user_id." );
		}
	}

	/**
	 * Create a user.
	 *
	 * @synopsis <user-login> <user-email> [--role=<role>] [--user_pass=<password>] [--user_registered=<yyyy-mm-dd>] [--display_name=<name>] [--porcelain]
	 */
	public function create( $args, $assoc_args ) {
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

		$user_id = $this->_create( array(
			'user_email' => $user_email,
			'user_login' => $user_login,
			'user_pass' => $user_pass,
			'user_registered' => $user_registered,
			'display_name' => $display_name,
			'role' => $role,
		) );

		if ( is_wp_error( $user_id ) ) {
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

	protected function _create( $params ) {
		return wp_insert_user( $params );
	}

	/**
	 * Update a user.
	 *
	 * @synopsis <id>... --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {
		parent::update( $args, $assoc_args, 'user' );
	}

	protected function _update( $params ) {
		return wp_update_user( $params );
	}

	/**
	 * Generate users.
	 *
	 * @synopsis [--count=<number>] [--role=<role>]
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
	 * Set the user role (for a particular blog).
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
	 * Add a role for a user.
	 *
	 * @subcommand add-role
	 * @synopsis <user-login> <role> [--blog=<blog>]
	 */
	public function add_role( $args, $assoc_args ) {
		$user = self::get_user_from_first_arg( $args[0] );

		$role = $args[1];

		$user->add_role( $role );

		WP_CLI::success( sprintf( "Added '%s' role for %s (%d).", $role, $user->user_login, $user->ID ) );
	}

	/**
	 * Remove a user's role.
	 *
	 * @subcommand remove-role
	 * @synopsis <user-login> [<role>] [--blog=<blog>]
	 */
	public function remove_role( $args, $assoc_args ) {
		$user = self::get_user_from_first_arg( $args[0] );

		if ( isset( $args[1] ) ) {
			$role = $args[1];

			$user->remove_role( $role );

			WP_CLI::success( sprintf( "Removed '%s' role for %s (%d).", $role, $user->user_login, $user->ID ) );
		} else {
			// Multisite
			if ( function_exists( 'remove_user_from_blog' ) )
				remove_user_from_blog( $user->ID, get_current_blog_id() );
			else
				$user->remove_all_caps();

			WP_CLI::success( "Removed {$user->user_login} ({$user->ID}) from " . site_url() );
		}
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

		$blog_users = get_users();

		$filename = $args[0];

		foreach ( new \WP_CLI\Iterators\CSV( $filename ) as $i => $new_user ) {
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
			$existing_user = get_user_by( 'email', $new_user['user_email'] );

			if ( !$existing_user ) {
				$existing_user = get_user_by( 'login', $new_user['user_login'] );
			}

			if ( $existing_user ) {
				$new_user['ID'] = $existing_user->ID;
				$user_id = wp_update_user( $new_user );

				if ( !in_array( $existing_user->user_login, wp_list_pluck( $blog_users, 'user_login' ) ) &&  $new_user['role'] ) {
					add_user_to_blog( get_current_blog_id(), $existing_user->ID, $new_user['role'] );
					WP_CLI::line( "{$existing_user->user_login} added to blog as {$new_user['role']}" );
				}

			// Create the user
			} else {
				$user_id = wp_insert_user( $new_user );
			}

			if ( is_wp_error( $user_id ) ) {
				WP_CLI::warning( $user_id );
				continue;

			} else if ( $new_user['role'] === false ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			if (!empty($existing_user)) {
				WP_CLI::success( $new_user['user_login'] . " updated" );
			} else {
				WP_CLI::success( $new_user['user_login'] . " created" );
			}
		}
	}
}

WP_CLI::add_command( 'user', 'User_Command' );

