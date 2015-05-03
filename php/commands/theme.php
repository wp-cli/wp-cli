<?php

/**
 * Manage themes.
 *
 * @package wp-cli
 */
class Theme_Command extends \WP_CLI\CommandWithUpgrade {

	protected $item_type = 'theme';
	protected $upgrade_refresh = 'wp_update_themes';
	protected $upgrade_transient = 'update_themes';

	protected $obj_fields = array(
		'name',
		'status',
		'update',
		'version'
	);

	function __construct() {
		if ( is_multisite() ) {
			$this->obj_fields[] = 'enabled';
		}
		parent::__construct();

		$this->fetcher = new \WP_CLI\Fetchers\Theme;
	}

	protected function get_upgrader_class( $force ) {
		return $force ? '\\WP_CLI\\DestructiveThemeUpgrader' : 'Theme_Upgrader';
	}

	/**
	 * See the status of one or all themes.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>]
	 * : A particular theme to show the status for.
	 */
	function status( $args ) {
		parent::status( $args );
	}

	/**
	 * Search the wordpress.org theme repository.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : The string to search for.
	 *
	 * [--per-page=<per-page>]
	 * : Optional number of results to display. Defaults to 10.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each plugin.
	 *
	 * [--fields=<fields>]
	 * : Ask for specific fields from the API. Defaults to name,slug,author,rating. Acceptable values:
	 *
	 *     **name**: Theme Name
	 *     **slug**: Theme Slug
	 *     **version**: Current Version Number
	 *     **author**: Theme Author
	 *     **preview_url**: Theme Preview URL
	 *     **screenshot_url**: Theme Screenshot URL
	 *     **rating**: Theme Rating
	 *     **num_ratings**: Number of Theme Ratings
	 *     **homepage**: Theme Author's Homepage
	 *     **description**: Theme Description
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme search automattic --per-page=20
	 *
	 *     wp theme search automattic --fields=name,version,slug,rating,num_ratings,description
	 */
	public function search( $args, $assoc_args ) {
		parent::_search( $args, $assoc_args );
	}

	protected function status_single( $args ) {
		$theme = $this->fetcher->get_check( $args[0] );

		$status = $this->format_status( $this->get_status( $theme ), 'long' );

		$version = $theme->get('Version');
		if ( $this->has_update( $theme->get_stylesheet() ) )
			$version .= ' (%gUpdate available%n)';

		echo WP_CLI::colorize( \WP_CLI\Utils\mustache_render( 'theme-status.mustache', array(
			'slug' => $theme->get_stylesheet(),
			'status' => $status,
			'version' => $version,
			'name' => $theme->get('Name'),
			'author' => $theme->get('Author'),
		) ) );
	}

	protected function get_all_items() {
		return $this->get_item_list();
	}

	protected function get_status( $theme ) {
		return ( $this->is_active_theme( $theme ) ) ? 'active' : 'inactive';
	}

	/**
	 * Activate a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to activate.
	 */
	public function activate( $args = array() ) {
		$theme = $this->fetcher->get_check( $args[0] );

		$name = $theme->get('Name');

		if ( 'active' === $this->get_status( $theme ) ) {
			WP_CLI::success( "The '$name' theme is already active." );
			exit;
		}

		if ( $theme->get_stylesheet() != $theme->get_template() && ! $this->fetcher->get( $theme->get_template() ) ) {
			WP_CLI::error( "The '{$theme->get_stylesheet()}' theme cannot be activated without its parent, '{$theme->get_template()}'." );
		}

		switch_theme( $theme->get_template(), $theme->get_stylesheet() );

		if ( $this->is_active_theme( $theme ) ) {
			WP_CLI::success( "Switched to '$name' theme." );
		} else {
			WP_CLI::error( "Could not switch to '$name' theme." );
		}
	}

	/**
	 * Enable a theme in a multisite install.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to enable.
	 *
	 * [--network]
	 * : If set, the theme is enabled for the entire network
	 *
	 * [--activate]
	 * : If set, the theme is activated for the current site. Note that
	 * the "network" flag has no influence on this.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme enable twentythirteen
	 *
	 *     wp theme enable twentythirteen --network
	 *
	 *     wp theme enable twentythirteen --activate
	 */
	public function enable( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		$theme = $this->fetcher->get_check( $args[0] );
		$name = $theme->get( 'Name' );

		# If the --network flag is set, we'll be calling the (get|update)_site_option functions
		$_site = ! empty( $assoc_args['network'] ) ? '_site' : '';

		# Add the current theme to the allowed themes option or site option
		$allowed_themes = call_user_func( "get{$_site}_option", 'allowedthemes' );
		if ( empty( $allowed_themes ) )
			$allowed_themes = array();
		$allowed_themes[ $theme->get_stylesheet() ] = true;
		call_user_func( "update{$_site}_option", 'allowedthemes', $allowed_themes );

		if ( ! empty( $assoc_args['network'] ) )
			WP_CLI::success( "Network enabled the '$name' theme." );
		else
			WP_CLI::success( "Enabled the '$name' theme." );

		# If the --activate flag is set, activate the theme for the current site
		if ( ! empty( $assoc_args['activate'] ) ) {
			$this->activate( $args );
		}
	}

	/**
	 * Disable a theme in a multisite install.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to disable.
	 *
	 * [--network]
	 * : If set, the theme is disabled on the network level. Note that
	 * individual sites may still have this theme enabled if it was
	 * enabled for them independently.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme disable twentythirteen
	 *
	 *     wp theme disable twentythirteen --network
	 */
	public function disable( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		$theme = $this->fetcher->get_check( $args[0] );
		$name = $theme->get( 'Name' );

		# If the --network flag is set, we'll be calling the (get|update)_site_option functions
		$_site = ! empty( $assoc_args['network'] ) ? '_site' : '';

		# Add the current theme to the allowed themes option or site option
		$allowed_themes = call_user_func( "get{$_site}_option", 'allowedthemes' );
		if ( ! empty( $allowed_themes[ $theme->get_stylesheet() ] ) )
			unset( $allowed_themes[ $theme->get_stylesheet() ] );
		call_user_func( "update{$_site}_option", 'allowedthemes', $allowed_themes );

		if ( ! empty( $assoc_args['network'] ) )
			WP_CLI::success( "Network disabled the '$name' theme." );
		else
			WP_CLI::success( "Disabled the '$name' theme." );
	}

	private function is_active_theme( $theme ) {
		return $theme->get_stylesheet_directory() == get_stylesheet_directory();
	}

	/**
	 * Get the path to a theme or to the theme directory.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>]
	 * : The theme to get the path to. Path includes "style.css" file.
	 * If not set, will return the path to the themes directory.
	 *
	 * [--dir]
	 * : If set, get the path to the closest parent directory, instead of the
	 * theme's "style.css" file.
	 *
	 * ## EXAMPLES
	 *
	 *     cd $(wp theme path)
	 */
	public function path( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			$path = WP_CONTENT_DIR . '/themes';
		} else {
			$theme = $this->fetcher->get_check( $args[0] );

			$path = $theme->get_stylesheet_directory();

			if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'dir' ) )
				$path .= '/style.css';
		}

		WP_CLI::line( $path );
	}

	protected function install_from_repo( $slug, $assoc_args ) {
		$api = themes_api( 'theme_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		}

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) && wp_get_theme( $slug )->exists() ) {
			// We know this will fail, so avoid a needless download of the package.
			return new WP_Error( 'already_installed', 'Theme already installed.' );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', html_entity_decode( $api->name, ENT_QUOTES ), $api->version ) );
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'version' ) != 'dev' ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $api->download_link, $this->item_type, $api->slug, $api->version );
		}
		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		return $result;
	}

	protected function get_item_list() {
		$items = array();

		if ( is_multisite() ) {
			$site_enabled = get_option( 'allowedthemes' );
			if ( empty( $site_enabled ) )
				$site_enabled = array();

			$network_enabled = get_site_option( 'allowedthemes' );
			if ( empty( $network_enabled ) )
				$network_enabled = array();
		}

		foreach ( wp_get_themes() as $key => $theme ) {
			$file = $theme->get_stylesheet_directory();
			$update_info = $this->get_update_info( $theme->get_stylesheet() );

			$items[ $file ] = array(
				'name' => $key,
				'status' => $this->get_status( $theme ),
				'update' => (bool) $update_info,
				'update_version' => $update_info['new_version'],
				'update_package' => $update_info['package'],
				'version' => $theme->get('Version'),
				'update_id' => $theme->get_stylesheet(),
				'title' => $theme->get('Name'),
				'description' => $theme->get('Description'),
			);

			if ( is_multisite() ) {
				if ( ! empty( $site_enabled[ $key ] ) && ! empty( $network_enabled[ $key ] ) )
					$items[ $file ]['enabled'] = 'network,site';
				elseif ( ! empty( $network_enabled[ $key ] ) )
					$items[ $file ]['enabled'] = 'network';
				elseif ( ! empty( $site_enabled[ $key ] ) )
					$items[ $file ]['enabled'] = 'site';
				else
					$items[ $file ]['enabled'] = 'no';
			}
		}

		return $items;
	}

	protected function filter_item_list( $items, $args ) {
		$theme_files = array();
		foreach ( $args as $arg ) {
			$theme_files[] = $this->fetcher->get_check( $arg )->get_stylesheet_directory();
		}

		return \WP_CLI\Utils\pick_fields( $items, $theme_files );
	}

	/**
	 * Install a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme|zip|url>...
	 * : A theme slug, the path to a local zip file, or URL to a remote zip file.
	 *
	 * [--version=<version>]
	 * : If set, get that particular version from wordpress.org, instead of the
	 * stable version.
	 *
	 * [--force]
	 * : If set, the command will overwrite any installed version of the theme, without prompting
	 * for confirmation.
	 *
	 * [--activate]
	 * : If set, the theme will be activated immediately after install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the latest version from wordpress.org and activate
	 *     wp theme install twentytwelve --activate
	 *
	 *     # Install from a local zip file
	 *     wp theme install ../my-theme.zip
	 *
	 *     # Install from a remote zip file
	 *     wp theme install http://s3.amazonaws.com/bucketname/my-theme.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 */
	function install( $args, $assoc_args ) {

		$theme_root = get_theme_root();
		if ( $theme_root && ! is_dir( $theme_root ) ) {
			wp_mkdir_p( $theme_root );
			register_theme_directory( $theme_root );
		}

		parent::install( $args, $assoc_args );
	}

	/**
	 * Get a theme
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole theme, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme get twentytwelve --format=json
	 */
	public function get( $args, $assoc_args ) {
		$theme = $this->fetcher->get_check( $args[0] );

		// WP_Theme object employs magic getter, unfortunately
		$theme_vars = array( 'name', 'title', 'version', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet', 'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri',
		);
		$theme_obj = new stdClass;
		foreach ( $theme_vars as $var ) {
			$theme_obj->$var = $theme->$var;
		}

		$theme_obj->description = wordwrap( $theme_obj->description );

		if ( empty( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = $theme_vars;
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $theme_obj );
	}

	/**
	 * Update one or more themes.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes to update.
	 *
	 * [--all]
	 * : If set, all themes that have updates will be updated.
	 *
	 * [--version=<version>]
	 * : If set, the plugin will be updated to the specified version.
	 *
	 * [--dry-run]
	 * : Preview which themes would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme update twentyeleven twentytwelve
	 *
	 *     wp theme update --all
	 */
	function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['version'] ) ) {
			foreach ( $this->fetcher->get_many( $args ) as $theme ) {
				$r = delete_theme( $theme->stylesheet );
				if ( is_wp_error( $r ) ) {
					WP_CLI::warning( $r );
				} else {
					$assoc_args['force'] = true;
					$this->install( array( $theme->stylesheet ), $assoc_args );
				}
			}
		} else {
			parent::update_many( $args, $assoc_args );
		}
	}

	/**
	 * Check if the theme is installed.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme is-installed twentytwelve
	 *
	 * @subcommand is-installed
	 */
	function is_installed( $args, $assoc_args = array() ) {
		$theme = wp_get_theme( $args[0] );

		if ( $theme->exists() ) {
			exit( 0 );
		} else {
			exit( 1 );
		}
	}

	/**
	 * Delete a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme>...
	 * : One or more themes to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme delete twentyeleven
	 *
	 * @alias uninstall
	 */
	function delete( $args ) {
		foreach ( $this->fetcher->get_many( $args ) as $theme ) {
			$theme_slug = $theme->get_stylesheet();

			if ( $this->is_active_theme( $theme ) ) {
				WP_CLI::warning( "Can't delete the currently active theme: $theme_slug" );
				continue;
			}

			$r = delete_theme( $theme_slug );

			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( $r );
			} else {
				WP_CLI::success( "Deleted '$theme_slug' theme." );
			}
		}
	}

	/**
	 * Get a list of themes.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Filter results based on the value of a field.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each theme.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each theme:
	 *
	 * * name
	 * * status
	 * * update
	 * * version
	 *
	 * These fields are optionally available:
	 *
	 * * update_version
	 * * update_package
	 * * update_id
	 * * title
	 * * description
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme list --status=inactive --format=csv
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		parent::_list( $_, $assoc_args );
	}
}

/**
 * Manage theme mods.
 *
 */
class Theme_Mod_command extends WP_CLI_Command {

	/**
	 * Get theme mod(s).
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to get.
	 *
	 * [--all]
	 * : List all theme mods
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme mod get --all
	 *     wp theme mod get background_color --format=json
	 *     wp theme mod get background_color header_textcolor
	 */
	public function get( $args = array(), $assoc_args = array() ) {

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( "You must specify at least one mod or use --all." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$args = array();
		}

		$list = array();
		$mods = get_theme_mods();
		if ( ! is_array( $mods ) ) {
			// if no mods are set (perhaps new theme), make sure foreach still works
			$mods = array();
		}
		foreach ( $mods as $k => $v ) {
			// if mods were given, skip the others
			if ( ! empty( $args ) && ! in_array( $k, $args ) ) continue;

			if ( is_array( $v ) ) {
				$list[] = array( 'key' => $k, 'value' => '=>' );
				foreach ( $v as $_k => $_v ) {
					$list[] = array( 'key' => "    $_k", 'value' => $_v );
				}
			} else {
				$list[] = array( 'key' => $k, 'value' => $v );
			}

		}

		// For unset mods, show blank value
		foreach ( $args as $mod ) {
			if ( ! isset( $mods[ $mod ] ) ) {
				$list[] = array( 'key' => $mod, 'value' => '' );
			}
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array('key', 'value'), 'thememods' );
		$formatter->display_items( $list );

	}

	/**
	 * Remove theme mod(s).
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to remove.
	 *
	 * [--all]
	 * : Remove all theme mods
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme mod remove --all
	 *     wp theme mod remove background_color
	 *     wp theme mod remove background_color header_textcolor
	 */
	public function remove( $args = array(), $assoc_args = array() ) {

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( "You must specify at least one mod or use --all." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			remove_theme_mods();
			WP_CLI::success( 'Theme mods removed.' );
			return;
		}

		foreach ( $args as $mod ) {
			remove_theme_mod( $mod );
		}

		WP_CLI::success( sprintf( '%d mods removed.', count( $args ) ) );

	}

	/**
	 * Set a theme mod.
	 *
	 * ## OPTIONS
	 *
	 * <mod>
	 * : The name of the theme mod to set or update.
	 *
	 * <value>
	 * : The new value.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme mod set background_color 000000
	 */
	public function set( $args = array(), $assoc_args = array() ) {
		list( $mod, $value ) = $args;

		set_theme_mod( $mod, $value );

		if ( $value == get_theme_mod( $mod ) ) {
			WP_CLI::success( sprintf( "Theme mod %s set to %s", $mod, $value ) );
		} else {
			WP_CLI::success( sprintf( "Could not update theme mod %s", $mod ) );
		}
	}

}

WP_CLI::add_command( 'theme', 'Theme_Command' );
WP_CLI::add_command( 'theme mod', 'Theme_Mod_Command' );

