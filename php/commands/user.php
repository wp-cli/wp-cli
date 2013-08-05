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
	 * ## OPTIONS
	 *
	 * --role=<role>
	 * : Only display users with a certain role.
	 *
	 * --fields=<fields>
	 * : Limit the output to specific object fields. Defaults to ID,user_login,display_name,user_email,user_registered,roles
	 *
	 * --format=<format>
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user list --format=ids
	 *
	 *     wp user list --role=administrator --format=csv
	 *
	 *     wp user list --fields=display_name,user_email
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
	 * Get a single user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * --format=<format>
	 * : The format to use when printing the user; acceptable values:
	 *
	 *     **table**: Outputs all fields of the user as a table.
	 *
	 *     **json**: Outputs all fields in JSON format.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user get 12
	 *
	 *     wp user get bob --format=json > bob.json
	 *
	 * @synopsis [--format=<format>] <user>
	 */
	public function get( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'format' => 'table'
		) );

		$user = self::get_user( $args[0] );

		if ( method_exists( $user, 'to_array' ) ) {
			$user_data = $user->to_array();
		} else {
			// WP 3.4 compat
			$user_data = (array) $user->data;
		}
		$user_data['roles'] = implode( ', ', $user->roles );

		switch ( $assoc_args['format'] ) {

		case 'table':
			$this->assoc_array_to_table( $user_data );
			break;

		case 'json':
			WP_CLI::print_value( $user_data, $assoc_args );
			break;

		default:
			\WP_CLI::error( "Invalid format: " . $assoc_args['format'] );
			break;

		}
	}

	/**
	 * Delete one or more users.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login or ID of the user to delete.
	 *
	 * --reassign=<ID>
	 * : User to reassign the posts to.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user delete 123 --reassign=567
	 *
	 * @synopsis <user>... [--reassign=<id>]
	 */
	public function delete( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'reassign' => null
		) );

		foreach( $args as $key => $arg ) {
			$args[$key] = self::get_user( $arg )->ID;
		}
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
	 * ## OPTIONS
	 *
	 * <user-login>
	 * : The login of the user to create.
	 *
	 * <user-email>
	 * : The email address of the user to create.
	 *
	 * --role=<role>
	 * : The role of the user to create. Default: default role
	 *
	 * --user_pass=<password>
	 * : The user password. Default: randomly generated
	 *
	 * --user_registered=<yyyy-mm-dd>
	 * : The date the user registered. Default: current date
	 *
	 * --display_name=<name>
	 * : The display name.
	 *
	 * --porcelain
	 * : Output just the new user id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user create bob bob@example.com --role=author
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
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login or ID of the user to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update. For accepted fields, see wp_update_user().
	 *
	 * ## EXAMPLES
	 *
	 *     wp user update 123 --user_login=mary --display_name=Mary
	 *
	 *     wp user update mary --user_pass=marypass
	 *
	 * @synopsis <user>... --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {

		foreach( $args as $key => $arg ) {
			$args[$key] = self::get_user( $arg )->ID;
		}
		parent::update( $args, $assoc_args, 'user' );
	}

	protected function _update( $params ) {
		return wp_update_user( $params );
	}

	/**
	 * Generate users.
	 *
	 * ## OPTIONS
	 *
	 * --count=<number>
	 * : How many users to generate. Default: 100
	 *
	 * --role=<role>
	 * : The role of the generated users. Default: default role from WP
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

		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating users', $count );

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
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * [<role>]
	 * : Make the user have the specified role. If not passed, the default role is
	 * used.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user set-role bob author
	 *     wp user set-role 12 author
	 *
	 * @subcommand set-role
	 * @synopsis <user> [<role>]
	 */
	public function set_role( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );

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
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * <role>
	 * : Add the specified role to the user.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user set-role bob author
	 *     wp user set-role 12 author
	 *
	 * @subcommand add-role
	 * @synopsis <user> <role>
	 */
	public function add_role( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );

		$role = $args[1];

		$user->add_role( $role );

		WP_CLI::success( sprintf( "Added '%s' role for %s (%d).", $role, $user->user_login, $user->ID ) );
	}

	/**
	 * Remove a user's role.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user remove-role bob
	 *     wp user remove-role 12
	 *
	 * @subcommand remove-role
	 * @synopsis <user> [<role>]
	 */
	public function remove_role( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );

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
	
	/**
	 * Add a capability for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * <cap>
	 * : Add the specified capability for the user.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user add-cap john create_premium_item
	 *     wp user add-cap 15 edit_product
	 *
	 * @subcommand add-cap
	 * @synopsis <user> <cap>
	 */
	public function add_cap( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );
		$cap  = $args[1];	

		$user->add_cap( $cap );

		WP_CLI::success( sprintf( "Added '%s' capability for %s (%d).", $cap, $user->user_login, $user->ID ) );
	}
	
	/**
	 * Remove a user's capability.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * <cap>
	 * : Capability to be removed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user remove-cap bob edit_themes
	 *     wp user remove-cap 11 publish_newsletters
	 *
	 * @subcommand remove-cap
	 * @synopsis <user> <cap>
	 */
	public function remove_cap( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );
		$cap = $args[1];

		$user->remove_cap( $cap );

		WP_CLI::success( sprintf( "Removed '%s' cap for %s (%d).", $cap, $user->user_login, $user->ID ) );
	}
	
	/**
	 * List all user's capabilities.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or user login.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user list-caps admin
	 *     wp user list-caps 21
	 *
	 * @subcommand list-caps
	 * @synopsis <user>
	 */
	public function list_caps( $args, $assoc_args ) {
		$user = self::get_user( $args[0] );
		$user->get_role_caps();
		
		$user_caps_list = $user->allcaps;
		$cap_table_titles = array( 'capability', 'status' );
				
		WP_CLI::success( "User caps (role and individual) are: " );
		
		foreach( $user_caps_list as $cap => $active ) {
			if( $active ) {
				\cli\line( $cap );
			}
		}
	}

	private static function get_user( $id_or_login ) {
		if ( is_numeric( $id_or_login ) )
			$user = get_user_by( 'id', $id_or_login );
		else
			$user = get_user_by( 'login', $id_or_login );

		if ( ! $user ) {
			WP_CLI::warning( "Invalid user ID or login: $id_or_login" );
		}

		return $user;
	}

	/**
	 * Import users from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The CSV file of users to import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user import-csv /path/to/users.csv
	 *
	 *     Sample users.csv file:
	 *
	 *     user_login,user_email,display_name,role
	 *     bobjones,bobjones@domain.com,Bob Jones,contributor
	 *     newuser1,newuser1@domain.com,New User,author
	 *     existinguser,existinguser@domain.com,Existing User,administrator
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
					WP_CLI::log( "{$existing_user->user_login} added as {$new_user['role']}." );
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

