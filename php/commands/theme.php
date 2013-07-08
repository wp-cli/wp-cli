<?php

/**
 * Manage themes.
 *
 * @package wp-cli
 */
class Theme_Command extends \WP_CLI\CommandWithUpgrade {

	protected $item_type = 'theme';
	protected $upgrader = 'Theme_Upgrader';
	protected $upgrade_refresh = 'wp_update_themes';
	protected $upgrade_transient = 'update_themes';

	protected $fields = array(
		'name',
		'status',
		'update',
		'version'
	);

	/**
	 * See the status of one or all themes.
	 *
	 * @synopsis [<theme>]
	 */
	function status( $args ) {
		parent::status( $args );
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
			if ( null === maybe_unserialize( $api->get_error_data() ) )
				WP_CLI::error( "Can't find the theme in the WordPress.org repository." );
			else
				WP_CLI::error( $api );
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		}

		// Check to see if we should update, rather than install.
		if ( $this->has_update( $slug ) ) {
			WP_CLI::log( sprintf( 'Updating %s (%s)', $api->name, $api->version ) );
			$result = WP_CLI\Utils\get_upgrader( $this->upgrader )->upgrade( $slug );

			/**
			 *  Else, if there's no update, it's either not installed,
			 *  or it's newer than what we've got.
			 */
		} else if ( !wp_get_theme( $slug )->exists() ) {
			WP_CLI::log( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );
			$result = WP_CLI\Utils\get_upgrader( $this->upgrader )->install( $api->download_link );
		} else {
			WP_CLI::error( 'Theme already installed and up to date.' );
		}

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
	 * @synopsis <theme|zip|url> [--version=<version>] [--activate]
	 */
	function install( $args, $assoc_args ) {
		parent::install( $args, $assoc_args );
	}

	/**
	 * Update a theme.
	 *
	 * @synopsis <theme> [--version=<version>]
	 */
	function update( $args, $assoc_args ) {
		$theme = $this->parse_name( $args[0] );

		parent::_update( $theme->get_stylesheet() );
	}

	/**
	 * Update all themes.
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

