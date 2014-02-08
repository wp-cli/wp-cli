<?php

/**
 * Manage user roles.
 *
 * @package wp-cli
 */
class Role_Command extends WP_CLI_Command {

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
	 * : Limit the output to specific object fields. Defaults to name,role.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp role list --fields=role --format=csv
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
	 *     wp role exists editor
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
	 * ## EXAMPLES
	 *
	 *     wp role create approver Approver
	 *
	 *     wp role create productadmin "Product Administrator"
	 */
	public function create( $args ) {
		self::persistence_check();

		$role_key = array_shift( $args );
		$role_name = array_shift( $args );

		if ( empty( $role_key ) || empty( $role_name ) )
			WP_CLI::error( "Can't create role, insufficient information provided.");

		if ( ! add_role( $role_key, $role_name ) )
			WP_CLI::error( "Role couldn't be created." );
		else
			WP_CLI::success( sprintf( "Role with key %s created.", $role_key ) );
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
	 *     wp role delete approver
	 *
	 *     wp role delete productadmin
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
	 * <role-key>
	 * : The internal name of the role.
	 *
	 * ## EXAMPLES
	 *
	 *     wp role reset administrator
	 *
	 *     wp role reset author
	 */
	public function reset( $args ) {
		global $wp_roles;

		self::persistence_check();

		$role_key = array_shift( $args );

		if ( empty( $role_key ) )
			WP_CLI::error( "Role key not provided, or is invalid." );

		switch ( $role_key ) {
			case 'administrator':
			remove_role( $role_key );
			add_role('administrator', 'Administrator');
			$role = get_role('administrator');
			$role->add_cap('switch_themes');
			$role->add_cap('edit_themes');
			$role->add_cap('activate_plugins');
			$role->add_cap('edit_plugins');
			$role->add_cap('edit_users');
			$role->add_cap('edit_files');
			$role->add_cap('manage_options');
			$role->add_cap('moderate_comments');
			$role->add_cap('manage_categories');
			$role->add_cap('manage_links');
			$role->add_cap('upload_files');
			$role->add_cap('import');
			$role->add_cap('unfiltered_html');
			$role->add_cap('edit_posts');
			$role->add_cap('edit_others_posts');
			$role->add_cap('edit_published_posts');
			$role->add_cap('publish_posts');
			$role->add_cap('edit_pages');
			$role->add_cap('read');
			$role->add_cap('level_10');
			$role->add_cap('level_9');
			$role->add_cap('level_8');
			$role->add_cap('level_7');
			$role->add_cap('level_6');
			$role->add_cap('level_5');
			$role->add_cap('level_4');
			$role->add_cap('level_3');
			$role->add_cap('level_2');
			$role->add_cap('level_1');
			$role->add_cap('level_0');
			$role->add_cap('edit_others_pages');
			$role->add_cap('edit_published_pages');
			$role->add_cap('publish_pages');
			$role->add_cap('delete_pages');
			$role->add_cap('delete_others_pages');
			$role->add_cap('delete_published_pages');
			$role->add_cap('delete_posts');
			$role->add_cap('delete_others_posts');
			$role->add_cap('delete_published_posts');
			$role->add_cap('delete_private_posts');
			$role->add_cap('edit_private_posts');
			$role->add_cap('read_private_posts');
			$role->add_cap('delete_private_pages');
			$role->add_cap('edit_private_pages');
			$role->add_cap('read_private_pages');
			$role->add_cap('delete_users');
			$role->add_cap('create_users');
			$role->add_cap('unfiltered_upload');
			$role->add_cap('edit_dashboard');
			$role->add_cap('update_plugins');
			$role->add_cap('delete_plugins');
			$role->add_cap('install_plugins');
			$role->add_cap('update_themes');
			$role->add_cap('install_themes');
			$role->add_cap('update_core');
			$role->add_cap('list_users');
			$role->add_cap('remove_users');
			$role->add_cap( 'promote_users' );
			$role->add_cap( 'edit_theme_options' );
			$role->add_cap( 'delete_themes' );
			$role->add_cap( 'export' );
			// Never used, will be removed. create_users or
			// promote_users is the capability you're looking for.
			$role->add_cap( 'add_users' );
			break;

			case 'editor':
			remove_role( $role_key );
			add_role('editor', 'Editor');
			$role = get_role('editor');
			$role->add_cap('moderate_comments');
			$role->add_cap('manage_categories');
			$role->add_cap('manage_links');
			$role->add_cap('upload_files');
			$role->add_cap('unfiltered_html');
			$role->add_cap('edit_posts');
			$role->add_cap('edit_others_posts');
			$role->add_cap('edit_published_posts');
			$role->add_cap('publish_posts');
			$role->add_cap('edit_pages');
			$role->add_cap('read');
			$role->add_cap('level_7');
			$role->add_cap('level_6');
			$role->add_cap('level_5');
			$role->add_cap('level_4');
			$role->add_cap('level_3');
			$role->add_cap('level_2');
			$role->add_cap('level_1');
			$role->add_cap('level_0');
			$role->add_cap('edit_others_pages');
			$role->add_cap('edit_published_pages');
			$role->add_cap('publish_pages');
			$role->add_cap('delete_pages');
			$role->add_cap('delete_others_pages');
			$role->add_cap('delete_published_pages');
			$role->add_cap('delete_posts');
			$role->add_cap('delete_others_posts');
			$role->add_cap('delete_published_posts');
			$role->add_cap('delete_private_posts');
			$role->add_cap('edit_private_posts');
			$role->add_cap('read_private_posts');
			$role->add_cap('delete_private_pages');
			$role->add_cap('edit_private_pages');
			$role->add_cap('read_private_pages');
			break;

			case 'author':
			remove_role( $role_key );
			add_role('author', 'Author');
			$role = get_role('author');
			$role->add_cap('upload_files');
			$role->add_cap('edit_posts');
			$role->add_cap('edit_published_posts');
			$role->add_cap('publish_posts');
			$role->add_cap('read');
			$role->add_cap('level_2');
			$role->add_cap('level_1');
			$role->add_cap('level_0');
			$role->add_cap('delete_posts');
			$role->add_cap('delete_published_posts');
			break;

			case 'contributor':
			remove_role( $role_key );
			add_role('contributor', 'Contributor');
			$role = get_role('contributor');
			$role->add_cap('edit_posts');
			$role->add_cap('read');
			$role->add_cap('level_1');
			$role->add_cap('level_0');
			$role->add_cap('delete_posts');
			break;

			case 'subscriber':
			remove_role( $role_key );
			add_role('subscriber', 'Subscriber');
			$role = get_role('subscriber');
			$role->add_cap('read');
			$role->add_cap('level_0');
			break;

			default:
			WP_CLI::error( 'Must specify a default role to reset.' );

		}

		WP_CLI::success( sprintf( "Role with key %s reset.", $role_key ) );

	}

	private static function persistence_check() {
		global $wp_roles;

		if ( !$wp_roles->use_db )
			WP_CLI::error( "Role definitions are not persistent." );
	}
}

WP_CLI::add_command( 'role', 'Role_Command' );
