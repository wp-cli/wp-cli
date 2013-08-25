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

	protected $fields = array(
		'name',
		'status',
		'update',
		'version'
	);

	protected function get_upgrader_class( $force ) {
		return $force ? '\\WP_CLI\\DestructiveThemeUpgrader' : 'Theme_Upgrader';
	}

	/**
	 * See the status of one or all themes.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : A particular theme to show the status for.
	 *
	 * @synopsis [<theme>]
	 */
	function status( $args ) {
		parent::status( $args );
	}

	/**
	 * Search wordpress.org theme repo
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : A particular theme to search for.
	 *
	 * --per-page
	 * : Optional number of results to display. Defaults to 10.
	 *
	 * --fields
	 * : Ask for specific fields from the API. Defaults to name,slug,author,rating. acceptable values:
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
	 * ## EXAMPLES
	 *
	 *     wp theme search automattic --per-page=20
	 *
	 *     wp theme search automattic --fields=name,version,slug,rating,num_ratings,description
	 *
	 * @synopsis <theme> [--per-page=<per-page>] [--fields=<fields>]
	 */
	public function search( $args, $assoc_args = array() ) {
		$term = $args[0];
		$per_page = isset( $assoc_args['per-page'] ) ? (int) $assoc_args['per-page'] : 10;
		$fields = isset( $assoc_args['fields'] ) ? $assoc_args['fields'] : array( 'name', 'slug', 'author', 'rating' );

		$api = themes_api( 'query_themes', array(
			'per_page' => $per_page,
			'search' => $term,
		) );

		parent::_search( $api, $fields, $assoc_args, 'theme' );

	}

	protected function status_single( $args ) {
		$theme = $this->parse_name( $args[0] );

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
	 *
	 * @synopsis <theme>
	 */
	public function activate( $args = array() ) {
		$theme = $this->parse_name( $args[0] );

		switch_theme( $theme->get_template(), $theme->get_stylesheet() );

		$name = $theme->get('Name');

		if ( $this->is_active_theme( $theme ) ) {
			WP_CLI::success( "Switched to '$name' theme." );
		} else {
			WP_CLI::error( "Could not switch to '$name' theme." );
		}
	}

	private function is_active_theme( $theme ) {
		return $theme->get_stylesheet_directory() == get_stylesheet_directory();
	}

	/**
	 * Get the path to a theme or to the theme directory.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to get the path to. Path includes "style.css" file.
	 * If not set, will return the path to the themes directory.
	 *
	 * --dir
	 * : If set, get the path to the closest parent directory, instead of the
	 * theme's "style.css" file.
	 *
	 * ## EXAMPLES
	 *
	 *     cd $(wp theme path)
	 *
	 * @synopsis [<theme>] [--dir]
	 */
	public function path( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			$path = WP_CONTENT_DIR . '/themes';
		} else {
			$theme = $this->parse_name( $args[0] );

			$path = $theme->get_stylesheet_directory();

			if ( !isset( $assoc_args['dir'] ) )
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

		if ( !isset( $assoc_args['force'] ) && wp_get_theme( $slug )->exists() ) {
			// We know this will fail, so avoid a needless download of the package.
			return new WP_Error( 'already_installed', "$slug already installed." );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );
		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		return $result;
	}

	protected function get_item_list() {
		$items = array();

		foreach ( wp_get_themes() as $key => $theme ) {
			$file = $theme->get_stylesheet_directory();

			$items[ $file ] = array(
				'name' => $key,
				'status' => $this->get_status( $theme ),
				'update' => $this->has_update( $theme->get_stylesheet() ),
				'version' => $theme->get('Version'),
				'update_id' => $theme->get_stylesheet(),
			);
		}

		return $items;
	}

	protected function filter_item_list( $items, $args ) {
		$theme_files = array();
		foreach ( $args as $arg ) {
			$theme_files[] = $this->parse_name( $arg )->get_stylesheet_directory();
		}

		return \WP_CLI\Utils\pick_fields( $items, $theme_files );
	}

	/**
	 * Install a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme|zip|url>
	 * : A theme slug, the path to a local zip file, or URL to a remote zip file.
	 *
	 * --force
	 * : If set, the command will overwrite any installed version of the theme, without prompting
	 * for confirmation.
	 *
	 * --activate
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
	 *
	 * @synopsis <theme|zip|url>... [--version=<version>] [--force] [--activate]
	 */
	function install( $args, $assoc_args ) {
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
	 * * --format=<format>
	 * : The format to use when printing the theme, acceptable values:
	 *
	 *   - **table**: Outputs all fields of the theme as a table.
	 *
	 *   - **json**: Outputs all fields in JSON format.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme get twentytwelve
	 *
	 * @synopsis <theme> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {

		$defaults = array(
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$theme = $this->parse_name( $args[0] );

		// WP_Theme object employs magic getter, unfortunately
		$theme_vars = array( 'name', 'title', 'version', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet', 'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri',
		);
		$theme_obj = new stdClass;
		foreach( $theme_vars as $var ) {
			$theme_obj->$var = $theme->$var;
		}

		switch ( $assoc_args['format'] ) {

			case 'table':
				unset( $theme_obj->tags );
				$fields = get_object_vars( $theme_obj );
				\WP_CLI\Utils\assoc_array_to_table( $fields );
				break;

			case 'json':
				WP_CLI::print_value( $theme_obj, $assoc_args );
				break;

			default:
				\WP_CLI::error( "Invalid format: " . $assoc_args['format'] );
				break;
		}
	}

	/**
	 * Update one or more themes.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme(s) to update.
	 *
	 * --all
	 * : If set, all themes that have updates will be updated.
	 *
	 * --version=dev
	 * : If set, the theme will be updated to the latest development version,
	 * regardless of what version is currently installed.
	 *
	 * --dry-run
	 * : Preview which themes would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme update twentyeleven twentytwelve
	 *
	 *     wp theme update --all
	 *
	 * @synopsis <theme>... [--version=<version>] [--all] [--dry-run]
	 */
	function update( $args, $assoc_args ) {
		parent::update_many( $args, $assoc_args );
	}

	/**
	 * Delete a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme delete twentyeleven
	 *
	 * @synopsis <theme>
	 */
	function delete( $args ) {
		$theme = $this->parse_name( $args[0] );
		$theme_slug = $theme->get_stylesheet();

		if ( $this->is_active_theme( $theme ) ) {
			WP_CLI::error( "Can't delete the currently active theme." );
		}

		$r = delete_theme( $theme_slug );

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r );
		}

		WP_CLI::success( sprintf( "Deleted '%s' theme.", $theme_slug ) );
	}

	/**
	 * Get a list of themes.
	 *
	 * ## OPTIONS
	 *
	 * * `--format`=<format>:
	 *
	 *     Output list as table, CSV or JSON. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme list --format=csv
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	function _list( $_, $assoc_args ) {
		parent::_list( $_, $assoc_args );
	}

	/**
	 * Parse the name of a plugin to a filename; check if it exists.
	 *
	 * @param string name
	 * @return object
	 */
	private function parse_name( $name ) {
		$theme = wp_get_theme( $name );

		if ( !$theme->exists() ) {
			WP_CLI::error( "The theme '$name' could not be found." );
			exit;
		}

		return $theme;
	}

}

WP_CLI::add_command( 'theme', 'Theme_Command' );

