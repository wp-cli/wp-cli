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

		parent::_search( $api, $fields, 'theme' );

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
	 * : The theme to get the path to. If not set, will return the path to the
	 * themes directory.
	 *
	 * --dir
	 * : If set, get the path to the closest parent directory, instead of the
	 * theme file.
	 *
	 * ## EXAMPLES
	 *
	 *     cd $(wp theme path)
	 *
	 * @synopsis [<theme>] [--dir]
	 */
	function path( $args, $assoc_args ) {
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
		$result = NULL;

		$api = themes_api( 'theme_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			WP_CLI::error( $api );
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		}

		if ( !isset( $assoc_args['force'] ) && wp_get_theme( $slug )->exists() ) {
			// We know this will fail, so avoid a needless download of the package.
			WP_CLI::error( 'Theme already installed.' );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );
		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		// Finally, activate theme if requested.
		if ( $result && isset( $assoc_args['activate'] ) ) {
			WP_CLI::log( "Activating '$slug'..." );
			$this->activate( array( $slug ) );
		}
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
	 * @synopsis <theme|zip|url> [--version=<version>] [--force] [--activate]
	 */
	function install( $args, $assoc_args ) {
		parent::install( $args, $assoc_args );
	}

	/**
	 * Update a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : The theme to update.
	 *
	 * --version=dev
	 * : If set, the theme will be updated to the latest development version,
	 * regardless of what version is currently installed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme update twentytwelve
	 *
	 * @synopsis <theme> [--version=<version>]
	 */
	function update( $args, $assoc_args ) {
		$theme = $this->parse_name( $args[0] );

		call_user_func( $this->upgrade_refresh );

		$this->get_upgrader( $assoc_args )->upgrade( $theme->get_stylesheet() );
	}

	/**
	 * Update all themes.
	 *
	 * ## OPTIONS
	 *
	 * --dry-run
	 * : Pretend to do the updates, to see what would happen.
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme update-all
	 *
	 * @subcommand update-all
	 * @synopsis [--dry-run]
	 */
	function update_all( $args, $assoc_args ) {
		parent::update_all( $args, $assoc_args );
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

