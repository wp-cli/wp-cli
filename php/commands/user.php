<?php

/**
 * Manage users.
 *
 * ## EXAMPLES
 *
 *     # List user IDs
 *     $ wp user list --field=ID
 *     1
 *
 *     # Create a new user.
 *     $ wp user create bob bob@example.com --role=author
 *     Success: Created user 3.
 *     Password: k9**&I4vNH(&
 *
 *     # Update an existing user.
 *     $ wp user update 123 --display_name=Mary --user_pass=marypass
 *     Success: Updated user 123.
 *
 *     # Delete user 123 and reassign posts to user 567
 *     $ wp user delete 123 --reassign=567
 *     Success: Removed user 123 from http://example.com
 *
 * @package wp-cli
 */
class User_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'user';
	protected $obj_fields = array(
		'ID',
		'user_login',
		'display_name',
		'user_email',
		'user_registered',
		'roles'
	);

	private $cap_fields = array(
		'name'
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\User;
	}

	/**
	 * List users.
	 *
	 * ## OPTIONS
	 *
	 * [--role=<role>]
	 * : Only display users with a certain role.
	 *
	 * [--<field>=<value>]
	 * : Control output by one or more arguments of get_users().
	 *
	 * [--network]
	 * : List all users in the network for multisite.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each user.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each user:
	 *
	 * * ID
	 * * user_login
	 * * display_name
	 * * user_email
	 * * user_registered
	 * * roles
	 *
	 * These fields are optionally available:
	 *
	 * * user_pass
	 * * user_nicename
	 * * user_url
	 * * user_activation_key
	 * * user_status
	 * * spam
	 * * deleted
	 * * caps
	 * * cap_key
	 * * allcaps
	 * * filter
	 * * url
	 *
	 * ## EXAMPLES
	 *
	 *     # List user IDs
	 *     $ wp user list --field=ID
	 *     1
	 *
	 *     # List users with administrator role
	 *     $ wp user list --role=administrator --format=csv
	 *     ID,user_login,display_name,user_email,user_registered,roles
	 *     1,supervisor,supervisor,supervisor@gmail.com,"2016-06-03 04:37:00",administrator
	 *
	 *     # List users with only given fields
	 *     $ wp user list --fields=display_name,user_email --format=json
	 *     [{"display_name":"supervisor","user_email":"supervisor@gmail.com"}]
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ) {
			if ( ! is_multisite() ) {
				WP_CLI::error( 'This is not a multisite install.' );
			}
			$assoc_args['blog_id'] = 0;
			if ( isset( $assoc_args['fields'] ) ) {
				$fields = explode( ',', $assoc_args['fields'] );
				$assoc_args['fields'] = array_diff( $fields, array( 'roles' ) );
			} else {
				$assoc_args['fields'] = array_diff( $this->obj_fields, array( 'roles' ) );
			}
		}

		$formatter = $this->get_formatter( $assoc_args );

		if ( in_array( $formatter->format, array( 'ids', 'count' ) ) ) {
			$assoc_args['fields'] = 'ids';
		} else {
			$assoc_args['fields'] = 'all_with_meta';
		}

		$assoc_args['count_total'] = false;
		$assoc_args = self::process_csv_arguments_to_arrays( $assoc_args );
		$users = get_users( $assoc_args );

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', $users );
		} else if ( 'count' === $formatter->format ) {
			$formatter->display_items( $users );
		} else {
			$it = WP_CLI\Utils\iterator_map( $users, function ( $user ) {
				if ( !is_object( $user ) )
					return $user;

				$user->roles = implode( ',', $user->roles );
				$user->url = get_author_posts_url( $user->ID, $user->user_nicename );
				return $user;
			} );

			$formatter->display_items( $it );
		}
	}

	/**
	 * Get details about a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole user, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Get a specific subset of the user's fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get user
	 *     $ wp user get 12 --field=login
	 *     supervisor
	 *
	 *     # Get user and export to JSON file
	 *     $ wp user get bob --format=json > bob.json
	 */
	public function get( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );
		$user_data = $user->to_array();
		$user_data['roles'] = implode( ', ', $user->roles );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $user_data );
	}

	/**
	 * Delete one or more users from the current site.
	 *
	 * On multisite, `wp user delete` only removes the user from the current
	 * site. Include `--network` to also remove the user from the database, but
	 * make sure to reassign their posts prior to deleting the user.
	 *
	 * ## OPTIONS
	 *
	 * <user>...
	 * : The user login, user email, or user ID of the user(s) to delete.
	 *
	 * [--network]
	 * : On multisite, delete the user from the entire network.
	 *
	 * [--reassign=<user-id>]
	 * : User ID to reassign the posts to.
	 *
	 * [--yes]
	 * : Answer yes to any confirmation prompts.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete user 123 and reassign posts to user 567
	 *     $ wp user delete 123 --reassign=567
	 *     Success: Removed user 123 from http://example.com
	 *
	 *     # Delete all contributors and reassign their posts to user 2
	 *     $ wp user delete $(wp user list --role=contributor --field=ID) --reassign=2
	 *     Success: Removed user 813 from http://example.com
	 *     Success: Removed user 578 from http://example.com
	 */
	public function delete( $args, $assoc_args ) {
		$network = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) && is_multisite();
		$reassign = \WP_CLI\Utils\get_flag_value( $assoc_args, 'reassign' );

		if ( $network && $reassign ) {
			WP_CLI::error('Reassigning content to a different user is not supported on multisite.');
		}

		if ( !$reassign ) {
			WP_CLI::confirm( '--reassign parameter not passed. All associated posts will be deleted. Proceed?', $assoc_args );
		}

		$users = $this->fetcher->get_many( $args );

		parent::_delete( $users, $assoc_args, function ( $user ) use ( $network, $reassign ) {
			$user_id = $user->ID;

			if ( $network ) {
				$r = wpmu_delete_user( $user_id );
				$message = "Deleted user $user_id.";
			} else {
				$r = wp_delete_user( $user_id, $reassign );
				$message = "Removed user $user_id from " . home_url() . ".";
			}

			if ( $r ) {
				return array( 'success', $message );
			} else {
				return array( 'error', "Failed deleting user $user_id." );
			}
		} );
	}

	/**
	 * Create a new user.
	 *
	 * ## OPTIONS
	 *
	 * <user-login>
	 * : The login of the user to create.
	 *
	 * <user-email>
	 * : The email address of the user to create.
	 *
	 * [--role=<role>]
	 * : The role of the user to create. Default: default role
	 *
	 * [--user_pass=<password>]
	 * : The user password. Default: randomly generated
	 *
	 * [--user_registered=<yyyy-mm-dd>]
	 * : The date the user registered. Default: current date
	 *
	 * [--display_name=<name>]
	 * : The display name.
	 *
	 * [--first_name=<first_name>]
	 * : The user's first name.
	 *
	 * [--last_name=<last_name>]
	 * : The user's last name.
	 *
	 * [--send-email]
	 * : Send an email to the user with their new account details.
	 *
	 * [--porcelain]
	 * : Output just the new user id.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create user
	 *     $ wp user create bob bob@example.com --role=author
	 *     Success: Created user 3.
	 *     Password: k9**&I4vNH(&
	 */
	public function create( $args, $assoc_args ) {
		$user = new stdClass;

		list( $user->user_login, $user->user_email ) = $args;

		$assoc_args = wp_slash( $assoc_args );

		if ( username_exists( $user->user_login ) ) {
			WP_CLI::error( "The '{$user->user_login}' username is already registered." );
		}

		if ( !is_email( $user->user_email ) ) {
			WP_CLI::error( "The '{$user->user_email}' email address is invalid." );
		}

		$user->user_registered = \WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'user_registered',
			strftime( "%F %T", current_time('timestamp') )
		);

		$user->display_name = \WP_CLI\Utils\get_flag_value( $assoc_args, 'display_name', false );

		$user->first_name = \WP_CLI\Utils\get_flag_value( $assoc_args, 'first_name', false );

		$user->last_name = \WP_CLI\Utils\get_flag_value( $assoc_args, 'last_name', false );

		if ( isset( $assoc_args['user_pass'] ) ) {
			$user->user_pass = $assoc_args['user_pass'];
		} else {
			$user->user_pass = wp_generate_password();
			$generated_pass = true;
		}

		$user->role = \WP_CLI\Utils\get_flag_value( $assoc_args, 'role', get_option('default_role') );
		self::validate_role( $user->role );

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'send-email' ) ) {
			add_filter( 'send_password_change_email', '__return_false' );
			add_filter( 'send_email_change_email', '__return_false' );
		}

		if ( is_multisite() ) {
			$ret = wpmu_validate_user_signup( $user->user_login, $user->user_email );
			if ( is_wp_error( $ret['errors'] ) && ! empty( $ret['errors']->errors ) ) {
				WP_CLI::error( $ret['errors'] );
			}
			$user_id = wpmu_create_user( $user->user_login, $user->user_pass, $user->user_email );
			if ( ! $user_id ) {
				WP_CLI::error( "Unknown error creating new user." );
			}
			$user->ID = $user_id;
			$user_id = wp_update_user( $user );
			if ( is_wp_error( $user_id ) ) {
				WP_CLI::error( $user_id );
			}
		} else {
			$user_id = wp_insert_user( $user );
		}

		if ( ! $user_id || is_wp_error( $user_id ) ) {
			if ( ! $user_id ) {
				$user_id = 'Unknown error creating new user.';
			}
			WP_CLI::error( $user_id );
		} else {
			if ( false === $user->role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'send-email' ) ) {
			self::wp_new_user_notification( $user_id, $user->user_pass );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $user_id );
		} else {
			WP_CLI::success( "Created user $user_id." );
			if ( isset( $generated_pass ) )
				WP_CLI::line( "Password: $user->user_pass" );
		}
	}

	/**
	 * Update an existing user.
	 *
	 * ## OPTIONS
	 *
	 * <user>...
	 * : The user login, user email or user ID of the user(s) to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update. For accepted fields, see wp_update_user().
	 *
	 * ## EXAMPLES
	 *
	 *     # Update user
	 *     $ wp user update 123 --display_name=Mary --user_pass=marypass
	 *     Success: Updated user 123.
	 */
	public function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['user_login'] ) ) {
			WP_CLI::warning( "User logins can't be changed." );
			unset( $assoc_args['user_login'] );
		}

		$user_ids = array();
		foreach ( $this->fetcher->get_many( $args ) as $user ) {
			$user_ids[] = $user->ID;
		}

		$assoc_args = wp_slash( $assoc_args );
		parent::_update( $user_ids, $assoc_args, 'wp_update_user' );
	}

	/**
	 * Generate some users.
	 *
	 * Creates a specified number of new users with dummy data.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many users to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--role=<role>]
	 * : The role of the generated users. Default: default role from WP
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: progress
	 * options:
	 *   - progress
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add meta to every generated users.
	 *     $ wp user generate --format=ids --count=3 | xargs -d ' ' -I % wp user meta add % foo bar
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 */
	public function generate( $args, $assoc_args ) {
		global $blog_id;

		$defaults = array(
			'count' => 100,
			'role' => get_option('default_role'),
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$role = $assoc_args['role'];

		if ( ! empty( $role ) ) {
			self::validate_role( $role );
		}

		$user_count = count_users();
		$total = $user_count['total_users'];
		$limit = $assoc_args['count'] + $total;

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating users', $assoc_args['count'] );
		}

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

			if ( 'progress' === $format ) {
				$notify->tick();
			} else if ( 'ids' === $format ) {
				echo $user_id;
				if ( $i < $limit - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}

	/**
	 * Set the user role.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * [<role>]
	 * : Make the user have the specified role. If not passed, the default role is
	 * used.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp user set-role 12 author
	 *     Success: Added johndoe (12) to http://example.com as author.
	 *
	 * @subcommand set-role
	 */
	public function set_role( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );

		$role = \WP_CLI\Utils\get_flag_value( $args, 1, get_option('default_role') );

		self::validate_role( $role );

		// Multisite
		if ( function_exists( 'add_user_to_blog' ) )
			add_user_to_blog( get_current_blog_id(), $user->ID, $role );
		else
			$user->set_role( $role );

		WP_CLI::success( "Added {$user->user_login} ({$user->ID}) to " . site_url() . " as {$role}." );
	}

	/**
	 * Add a role for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * <role>
	 * : Add the specified role to the user.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp user add-role 12 author
	 *     Success: Added 'author' role for johndoe (12).
	 *
	 * @subcommand add-role
	 */
	public function add_role( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );

		$role = $args[1];

		self::validate_role( $role );

		$user->add_role( $role );

		WP_CLI::success( sprintf( "Added '%s' role for %s (%d).", $role, $user->user_login, $user->ID ) );
	}

	/**
	 * Remove a user's role.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * [<role>]
	 * : A specific role to remove.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp user remove-role 12 author
	 *     Success: Removed 'author' role for johndoe (12).
	 *
	 * @subcommand remove-role
	 */
	public function remove_role( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );

		if ( isset( $args[1] ) ) {
			$role = $args[1];

			self::validate_role( $role );

			$user->remove_role( $role );

			WP_CLI::success( sprintf( "Removed '%s' role for %s (%d).", $role, $user->user_login, $user->ID ) );
		} else {
			// Multisite
			if ( function_exists( 'remove_user_from_blog' ) )
				remove_user_from_blog( $user->ID, get_current_blog_id() );
			else
				$user->remove_all_caps();

			WP_CLI::success( "Removed {$user->user_login} ({$user->ID}) from " . site_url() . "." );
		}
	}

	/**
	 * Add a capability to a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * <cap>
	 * : The capability to add.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a capability for a user
	 *     $ wp user add-cap john create_premium_item
	 *     Success: Added 'create_premium_item' capability for john (16).
	 *
	 *     # Add a capability for a user
	 *     $ wp user add-cap 15 edit_product
	 *     Success: Added 'edit_product' capability for johndoe (15).
	 *
	 * @subcommand add-cap
	 */
	public function add_cap( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );
		if ( $user ) {
			$cap  = $args[1];
			$user->add_cap( $cap );

			WP_CLI::success( sprintf( "Added '%s' capability for %s (%d).", $cap, $user->user_login, $user->ID ) );
		}
	}

	/**
	 * Remove a user's capability.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * <cap>
	 * : The capability to be removed.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp user remove-cap 11 publish_newsletters
	 *     Success: Removed 'publish_newsletters' cap for supervisor (11).
	 *
	 * @subcommand remove-cap
	 */
	public function remove_cap( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );
		if ( $user ) {
			$cap = $args[1];
			$user->remove_cap( $cap );

			WP_CLI::success( sprintf( "Removed '%s' cap for %s (%d).", $cap, $user->user_login, $user->ID ) );
		}
	}

	/**
	 * List all capabilities for a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or login.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp user list-caps 21
	 *     edit_product
	 *     create_premium_item
	 *
	 * @subcommand list-caps
	 */
	public function list_caps( $args, $assoc_args ) {
		$user = $this->fetcher->get_check( $args[0] );

		if ( $user ) {
			$user->get_role_caps();

			$user_caps_list = $user->allcaps;

			$active_user_cap_list = array();

			foreach ( $user_caps_list as $cap => $active ) {
				if ( $active ) {
					$active_user_cap_list[] = $cap;
				}
			}

			if ( 'list' === $assoc_args['format'] ) {
				foreach ( $active_user_cap_list as $cap ) {
					WP_CLI::line( $cap );
				}
			}
			else {
				$output_caps = array();
				foreach ( $active_user_cap_list as $cap ) {
					$output_cap = new stdClass;

					$output_cap->name = $cap;

					$output_caps[] = $output_cap;
				}
				$formatter = new \WP_CLI\Formatter( $assoc_args, $this->cap_fields );
				$formatter->display_items( $output_caps );
			}

		}
	}

	/**
	 * Import users from a CSV file.
	 *
	 * If the user already exists (matching the email address or login), then
	 * the user is updated unless the `--skip-update` flag is used.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The local or remote CSV file of users to import.
	 *
	 * [--send-email]
	 * : Send an email to new users with their account details.
	 *
	 * [--skip-update]
	 * : Don't update users that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import users from local CSV file
	 *     $ wp user import-csv /path/to/users.csv
	 *     Success: bobjones created
	 *     Success: newuser1 created
	 *     Success: existinguser created
	 *
	 *     # Import users from remote CSV file
	 *     $ wp user import-csv http://example.com/users.csv
	 *
	 *     Sample users.csv file:
	 *
	 *     user_login,user_email,display_name,role
	 *     bobjones,bobjones@example.com,Bob Jones,contributor
	 *     newuser1,newuser1@example.com,New User,author
	 *     existinguser,existinguser@example.com,Existing User,administrator
	 *
	 * @subcommand import-csv
	 */
	public function import_csv( $args, $assoc_args ) {

		$blog_users = get_users();

		$filename = $args[0];

		if ( 0 === stripos( $filename, 'http://' ) || 0 === stripos( $filename, 'https://' ) ) {
			$response = wp_remote_head( $filename );
			$response_code = (string)wp_remote_retrieve_response_code( $response );
			if ( in_array( $response_code[0], array( 4, 5 ) ) ) {
				WP_CLI::error( "Couldn't access remote CSV file (HTTP {$response_code} response)." );
			}
		} else if ( ! file_exists( $filename ) ) {
			WP_CLI::error( sprintf( "Missing file: %s", $filename ) );
		}

		foreach ( new \WP_CLI\Iterators\CSV( $filename ) as $i => $new_user ) {
			$defaults = array(
				'role' => get_option('default_role'),
				'user_pass' => wp_generate_password(),
				'user_registered' => strftime( "%F %T", time() ),
				'display_name' => false,
			);
			$new_user = array_merge( $defaults, $new_user );

			$secondary_roles = array();
			if ( ! empty( $new_user['roles'] ) ) {
				$roles = array_map( 'trim', explode( ',', $new_user['roles'] ) );
				$invalid_role = false;
				foreach( $roles as $role ) {
					if ( is_null( get_role( $role ) ) ) {
						WP_CLI::warning( "{$new_user['user_login']} has an invalid role." );
						$invalid_role = true;
						break;
					}
				}
				if ( $invalid_role ) {
					continue;
				}
				$new_user['role'] = array_shift( $roles );
				$secondary_roles = $roles;
			} else if ( 'none' === $new_user['role'] ) {
				$new_user['role'] = false;
			} elseif ( is_null( get_role( $new_user['role'] ) ) ) {
				WP_CLI::warning( "{$new_user['user_login']} has an invalid role." );
				continue;
			}

			// User already exists and we just need to add them to the site if they aren't already there
			$existing_user = get_user_by( 'email', $new_user['user_email'] );

			if ( !$existing_user ) {
				$existing_user = get_user_by( 'login', $new_user['user_login'] );
			}

			if ( $existing_user && \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-update' ) ) {

				WP_CLI::log( "{$existing_user->user_login} exists and has been skipped." );
				continue;

			} else if ( $existing_user ) {

				$new_user['ID'] = $existing_user->ID;
				$user_id = wp_update_user( $new_user );

				if ( !in_array( $existing_user->user_login, wp_list_pluck( $blog_users, 'user_login' ) ) && is_multisite() && $new_user['role'] ) {
					add_user_to_blog( get_current_blog_id(), $existing_user->ID, $new_user['role'] );
					WP_CLI::log( "{$existing_user->user_login} added as {$new_user['role']}." );
				}

			// Create the user
			} else {
				unset( $new_user['ID'] ); // Unset else it will just return the ID

				if ( is_multisite() ) {
					$ret = wpmu_validate_user_signup( $new_user['user_login'], $new_user['user_email'] );
					if ( is_wp_error( $ret['errors'] ) && ! empty( $ret['errors']->errors ) ) {
						WP_CLI::warning( $ret['errors'] );
						continue;
					}
					$user_id = wpmu_create_user( $new_user['user_login'], $new_user['user_pass'], $new_user['user_email'] );
					if ( ! $user_id ) {
						WP_CLI::warning( "Unknown error creating new user." );
						continue;
					}
					$new_user['ID'] = $user_id;
					$user_id = wp_update_user( $new_user );
					if ( is_wp_error( $user_id ) ) {
						WP_CLI::warning( $user_id );
						continue;
					}
				} else {
					$user_id = wp_insert_user( $new_user );
				}

				if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'send-email' ) ) {
					self::wp_new_user_notification( $user_id, $new_user['user_pass'] );
				}
			}

			if ( is_wp_error( $user_id ) ) {
				WP_CLI::warning( $user_id );
				continue;

			} else if ( $new_user['role'] === false ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			$user = get_user_by( 'id', $user_id );
			foreach( $secondary_roles as $secondary_role ) {
				$user->add_role( $secondary_role );
			}

			if ( !empty( $existing_user ) ) {
				WP_CLI::success( $new_user['user_login'] . " updated." );
			} else {
				WP_CLI::success( $new_user['user_login'] . " created." );
			}
		}
	}

	/**
	 * Check whether the role is valid
	 *
	 * @param string
	 */
	private static function validate_role( $role ) {

		if ( ! empty( $role ) && is_null( get_role( $role ) ) ) {
			WP_CLI::error( sprintf( "Role doesn't exist: %s", $role ) );
		}

	}

	/**
	 * Acommodate three different behaviors for wp_new_user_notification()
	 * - 4.3.1 and above: expect second argument to be deprecated
	 * - 4.3: Second argument was repurposed as $notify
	 * - Below 4.3: Send the password in the notification
	 *
	 * @param string $user_id
	 * @param string $password
	 */
	private static function wp_new_user_notification( $user_id, $password ) {
		if ( \WP_CLI\Utils\wp_version_compare( '4.3.1', '>=' ) ) {
			wp_new_user_notification( $user_id, null, 'both' );
		} else if ( \WP_CLI\Utils\wp_version_compare( '4.3', '>=' ) ) {
			wp_new_user_notification( $user_id, 'both' );
		} else {
			wp_new_user_notification( $user_id, $password );
		}
	}

}

WP_CLI::add_command( 'user', 'User_Command' );
