<?php

/**
 * Manage user roles.
 *
 * ## EXAMPLES
 *
 *     # List roles
 *     $ wp role list --fields=role --format=csv
 *     role
 *     administrator
 *     editor
 *     author
 *     contributor
 *     subscriber
 *
 *     # Check if a role exists
 *     $ wp role exists editor
 *     Success: Role with ID editor exists.
 *
 *     # Create role
 *     $ wp role create approver Approver
 *     Success: Role with key approver created.
 *
 *     # Delete role
 *     $ wp role delete approver
 *     Success: Role with key approver deleted.
 *
 *     # Reset role
 *     $ wp role reset administrator author contributor
 *     Success: Reset 3/3 roles
 *
 * @package wp-cli
 */
class Role_Command extends WP_CLI_Command {
	private $dry_run;
	private $fields = array(
		'name',
		'role'
	);

	/**
	 * List all roles.
	 *
	 * ## OPTIONS
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
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each role:
	 *
	 * * name
	 * * role
	 *
	 * There are no optional fields.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp role list --fields=role --format=csv
	 *     role
	 *     administrator
	 *     editor
	 *     author
	 *     contributor
	 *     subscriber
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		global $wp_roles;

		$output_roles = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$output_role = new stdClass;

			$output_role->name = $role['name'];
			$output_role->role = $key;

			$output_roles[] = $output_role;
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_roles );
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
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp role exists editor
	 *     Success: Role with ID editor exists.
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
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * <role-name>
	 * : The publicly visible name of the role.
	 *
	 * [--clone=<role>]
	 * : Clone capabilities from an existing role.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create role for Approver
	 *     $ wp role create approver Approver
	 *     Success: Role with key approver created.
	 *
	 *     # Create role for Product Administrator
	 *     $ wp role create productadmin "Product Administrator"
	 *     Success: Role with key productadmin created.
	 */
	public function create( $args, $assoc_args ) {
		global $wp_roles;

		self::persistence_check();

		$role_key = array_shift( $args );
		$role_name = array_shift( $args );

		if ( empty( $role_key ) || empty( $role_name ) ) {
			WP_CLI::error( "Can't create role, insufficient information provided.");
		}

		$capabilities = false;
		if ( ! empty( $assoc_args['clone'] ) ) {
			$role_obj = $wp_roles->get_role( $assoc_args['clone'] );
			if ( ! $role_obj ) {
				WP_CLI::error( "'{$assoc_args['clone']}' role not found." );
			}
			$capabilities = array_keys( $role_obj->capabilities );
		}

		if ( add_role( $role_key, $role_name ) ) {
			if ( ! empty( $capabilities ) ) {
				$role_obj = $wp_roles->get_role( $role_key );
				foreach( $capabilities as $cap ) {
					$role_obj->add_cap( $cap );
				}
				WP_CLI::success( sprintf( "Role with key %s created. Cloned capabilities from %s.", $role_key, $assoc_args['clone'] ) );
			} else {
				WP_CLI::success( sprintf( "Role with key %s created.", $role_key ) );
			}
		} else {
			WP_CLI::error( "Role couldn't be created." );
		}
	}

	/**
	 * Delete an existing role.
	 *
	 * ## OPTIONS
	 *
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete approver role
	 *     $ wp role delete approver
	 *     Success: Role with key approver deleted.
	 *
	 *     # Delete productadmin role
	 *     wp role delete productadmin
	 *     Success: Role with key productadmin deleted.
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

	/**
	 * Reset any default role to default capabilities.
	 *
	 * ## OPTIONS
	 *
	 * [<role-key>...]
	 * : The internal name of one or more roles to reset.
	 *
	 * [--all]
	 * : If set, all default roles will be reset.
	 *
	 * [--dry-run]
	 * : Run the entire reset operation and show report, but don't save
	 * changes to the database.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset role
	 *     $ wp role reset administrator author contributor
	 *     Success: Reset 1/3 roles.
	 *
	 *     # Reset all default roles
	 *     $ wp role reset --all
	 *     Success: All default roles reset.
	 */
	public function reset( $args, $assoc_args ) {

		self::persistence_check();

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		if ( ! function_exists( 'populate_roles' ) ) {
			require_once( ABSPATH.'wp-admin/includes/schema.php' );
		}

		global $wp_roles;
		$all_roles = array_keys( $wp_roles->roles );
		$preserve_args = $args;
		$this->dry_run         = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' );

		// get our default roles
		$default_roles = $preserve = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$before = array();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			foreach( $default_roles as $role ) {
				$before[ $role ] = get_role( $role );
				if ( !$this->dry_run ) {
					remove_role( $role );
				}
				$args[]= $role;
			}
			if ( !$this->dry_run ) {
				populate_roles();
			}
			$not_affected_roles = array_diff( $all_roles, $default_roles );
			if ( ! empty( $not_affected_roles ) ) {
				foreach ( $not_affected_roles as $not_affected_role ) {
					WP_CLI::log( "Custom role '{$not_affected_role}' not affected." );
				}
			}
		} else {

			foreach ( $args as $k => $role_key ) {
				$key = array_search( $role_key, $default_roles );
				if ( false !== $key ) {
					unset( $preserve[ $key ] );
					$before[ $role_key ] = get_role( $role_key );
					if ( !$this->dry_run ) {
						remove_role( $role_key );
					}
				} else {
					unset( $args[ $k ] );
				}
			}

			$not_affected_roles = array_diff( $preserve_args, $default_roles );
			if ( ! empty( $not_affected_roles ) ) {
				foreach ( $not_affected_roles as $not_affected_role ) {
					WP_CLI::log( "Custom role '{$not_affected_role}' not affected." );
				}
			}

			// no roles were unset, bail
			if ( count( $default_roles ) == count( $preserve ) ) {
				WP_CLI::error( 'Must specify a default role to reset.' );
			}

			if ( ! $this->dry_run ) {
				// for the roles we're not resetting
				foreach ( $preserve as $k => $role ) {
					/* save roles
					 * if get_role is null
					 * save role name for re-removal
					 */
					$roleobj        = get_role( $role );
					$preserve[ $k ] = is_null( $roleobj ) ? $role : $roleobj;

					remove_role( $role );
				}

				// put back all default roles and capabilities
				populate_roles();

				// restore the preserved roles
				foreach ( $preserve as $k => $roleobj ) {
					// re-remove after populating
					if ( is_a( $roleobj, 'WP_Role' ) ) {
						remove_role( $roleobj->name );
						add_role( $roleobj->name, ucwords( $roleobj->name ), $roleobj->capabilities );
					} else {
						// when not an object, that means the role didn't exist before
						remove_role( $roleobj );
					}
				}
			}
		}

		$num_reset = 0;
		$args = array_unique( $args );
		$num_to_reset = count( $args );
		foreach( $args as $role_key ) {
			$before_capabilities = $before[ $role_key ]->capabilities;
			$after_capabilities  = self::get_role_capability( $role_key );

			if ( $after_capabilities != $before_capabilities ) {
				++$num_reset;
				$restored_cap = array_diff_key( $after_capabilities, $before_capabilities );
				$removed_cap = array_diff_key( $before_capabilities, $after_capabilities );
				$restored_cap_count = count( $restored_cap );
				$removed_cap_count = count( $removed_cap );
				if ( ! $this->dry_run ) {
					WP_CLI::log( "Restored {$restored_cap_count} capabilities and removed {$removed_cap_count} capabilities from '{$role_key}' role." );
				} else {
					WP_CLI::log( "{$restored_cap_count} capabilities will restore and {$removed_cap_count} capabilities will remove from '{$role_key}' role." );
				}
			} else {
				WP_CLI::log( "No changes necessary for '{$role_key}' role." );
			}
		}
		if ( $num_reset ) {
			if ( 1 === count( $args ) ) {
				WP_CLI::success( 'Role reset.' );
			} else {
				WP_CLI::success( "{$num_reset} of {$num_to_reset} roles reset." );
			}
		} else {
			if ( 1 === count( $args ) ) {
				WP_CLI::success( 'Role didn\'t need resetting.' );
			} else {
				WP_CLI::success( 'No roles needed resetting.' );
			}
		}
	}

	private static function persistence_check() {
		global $wp_roles;

		if ( !$wp_roles->use_db )
			WP_CLI::error( "Role definitions are not persistent." );
	}

	private static function get_role_capability( $role_key ) {
		$capabilities = array();
		if ( 'administrator' == $role_key ) {
			$capabilities = array(
				// populate_roles_160
				'switch_themes'          => true,
				'edit_themes'            => true,
				'activate_plugins'       => true,
				'edit_plugins'           => true,
				'edit_users'             => true,
				'edit_files'             => true,
				'manage_options'         => true,
				'moderate_comments'      => true,
				'manage_categories'      => true,
				'manage_links'           => true,
				'upload_files'           => true,
				'import'                 => true,
				'unfiltered_html'        => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'publish_posts'          => true,
				'edit_pages'             => true,
				'read'                   => true,
				'level_10'               => true,
				'level_9'                => true,
				'level_8'                => true,
				'level_7'                => true,
				'level_6'                => true,
				'level_5'                => true,
				'level_4'                => true,
				'level_3'                => true,
				'level_2'                => true,
				'level_1'                => true,
				'level_0'                => true,

				// populate_roles_210
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_pages'           => true,
				'delete_others_pages'    => true,
				'delete_published_pages' => true,
				'delete_posts'           => true,
				'delete_others_posts'    => true,
				'delete_published_posts' => true,
				'delete_private_posts'   => true,
				'edit_private_posts'     => true,
				'read_private_posts'     => true,
				'delete_private_pages'   => true,
				'edit_private_pages'     => true,
				'read_private_pages'     => true,
				'delete_users'           => true,
				'create_users'           => true,

				//populate_roles_230
				'unfiltered_upload'      => true,

				//populate_roles_250
				'edit_dashboard'         => true,

				//populate_roles_260
				'update_plugins'         => true,
				'delete_plugins'         => true,

				//populate_roles_270
				'install_plugins'        => true,
				'update_themes'          => true,

				//populate_roles_280
				'install_themes'         => true,

				//populate_roles_300
				'update_core'            => true,
				'list_users'             => true,
				'remove_users'           => true,
				'promote_users'          => true,
				'edit_theme_options'     => true,
				'delete_themes'          => true,
				'export'                 => true

			);
			if ( \WP_CLI\Utils\wp_version_compare( '4.4', '<' ) ) {
				$capabilities[ "add_users" ] = true;
			}
		} elseif ( 'editor' == $role_key ) {
			$capabilities = array(
				// populate_roles_160
				'moderate_comments'      => true,
				'manage_categories'      => true,
				'manage_links'           => true,
				'upload_files'           => true,
				'unfiltered_html'        => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'publish_posts'          => true,
				'edit_pages'             => true,
				'read'                   => true,
				'level_7'                => true,
				'level_6'                => true,
				'level_5'                => true,
				'level_4'                => true,
				'level_3'                => true,
				'level_2'                => true,
				'level_1'                => true,
				'level_0'                => true,

				// populate_roles_210
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_pages'           => true,
				'delete_others_pages'    => true,
				'delete_published_pages' => true,
				'delete_posts'           => true,
				'delete_others_posts'    => true,
				'delete_published_posts' => true,
				'delete_private_posts'   => true,
				'edit_private_posts'     => true,
				'read_private_posts'     => true,
				'delete_private_pages'   => true,
				'edit_private_pages'     => true,
				'read_private_pages'     => true,

			);

		} elseif ( 'author' == $role_key ) {
			$capabilities = array(
				// populate_roles_160
				'upload_files'           => true,
				'edit_posts'             => true,
				'edit_published_posts'   => true,
				'publish_posts'          => true,
				'read'                   => true,
				'level_2'                => true,
				'level_1'                => true,
				'level_0'                => true,

				// populate_roles_210
				'delete_posts'           => true,
				'delete_published_posts' => true
			);
		} elseif ( 'contributor' == $role_key ) {
			$capabilities = array(
				// populate_roles_160
				'edit_posts'   => true,
				'read'         => true,
				'level_1'      => true,
				'level_0'      => true,

				// populate_roles_210
				'delete_posts' => true
			);
		} elseif ( 'subscriber' == $role_key ) {
			$capabilities = array(
				// populate_roles_160
				'read'    => true,
				'level_0' => true
			);
		}
		return $capabilities;
	}
}

WP_CLI::add_command( 'role', 'Role_Command' );
