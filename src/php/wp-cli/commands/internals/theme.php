<?php

WP_CLI::add_command('theme', 'Theme_Command');

/**
 * Implement theme command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Theme_Command extends WP_CLI_Command_With_Upgrade {

	protected $item_type = 'theme';
	protected $upgrader = 'Theme_Upgrader';
	protected $upgrade_refresh = 'wp_update_themes';
	protected $upgrade_transient = 'update_themes';

	// Show details about a single theme
	protected function status_single( $stylesheet, $name ) {
		$details = get_theme_data( $stylesheet );

		$status = $this->get_status( $stylesheet, true );

		$version = $details['Version'];

		if ( $this->get_update_status( $name ) )
			$version .= ' (%gUpdate available%n)';

		WP_CLI::line( 'Theme %9' . $name . '%n details:' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Version: ' . $version );
		WP_CLI::line( '    Author: ' . strip_tags( $details[ 'Author' ] ) );
	}

	// Show details about all themes
	protected function status_all() {
		// Print the header
		WP_CLI::line( 'Installed themes:' );

		foreach ( get_themes() as $key => $theme ) {
			if ( $this->get_update_status( $theme['Stylesheet'] ) ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$stylesheet = $this->get_stylesheet_path( $theme['Stylesheet'] );

			$line .= $this->get_status( $stylesheet ) . ' ' . $theme['Stylesheet'] . '%n';

			WP_CLI::line( $line );
		}

		// Print the footer
		WP_CLI::line();

		$legend = array(
			'I' => 'Inactive',
			'%gA' => 'Active',
		);

		WP_CLI::legend( $legend );
	}

	private function get_status( $stylesheet, $long = false ) {
		if ( $this->is_active_theme( $stylesheet ) ) {
			$line  = '%g';
			$line .= $long ? 'Active' : 'A';
		} else {
			$line  = $long ? 'Inactive' : 'I';
		}

		return $line;
	}

	/**
	 * Activate a theme
	 *
	 * @param array $args
	 **/
	public function activate( $args = array() ) {
		list( $stylesheet, $child ) = $this->parse_name( $args, __FUNCTION__ );

		$details = get_theme_data( $stylesheet );

		$parent = $details['Template'];

		if ( empty( $parent ) ) {
			$parent = $child;
		} elseif ( !is_readable( $this->get_stylesheet_path( $parent ) ) ) {
			WP_CLI::error( 'Parent theme not found.' );
		}

		switch_theme( $parent, $child );

		$name = $details['Title'];

		if ( $this->is_active_theme( $stylesheet ) ) {
			WP_CLI::success( "Switched to '$name' theme." );
		} else {
			WP_CLI::error( "Could not switch to '$name' theme." );
		}
	}

	private function is_active_theme( $stylesheet ) {
		return dirname( $stylesheet ) == get_stylesheet_directory();
	}

	/**
	 * Get a theme path
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function path( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			$path = WP_CONTENT_DIR . '/themes';
		} else {
			list( $stylesheet, $name ) = $this->parse_name( $args, __FUNCTION__ );
			$path = $stylesheet;

			if ( isset( $assoc_args['dir'] ) )
				$path = dirname( $path );
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

		// Check to see if we should update, rather than install.
		if ( $this->get_update_status( $slug ) ) {
			WP_CLI::line( sprintf( 'Updating %s (%s)', $api->name, $api->version ) );
			$result = WP_CLI::get_upgrader( $this->upgrader )->upgrade( $slug );

			/**
			 *  Else, if there's no update, it's either not installed,
			 *  or it's newer than what we've got.
			 */
		} else if ( !is_readable( $this->get_stylesheet_path( $slug ) ) ) {
			WP_CLI::line( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );
			$result = WP_CLI::get_upgrader( $this->upgrader )->install( $api->download_link );
		} else {
			WP_CLI::error( 'Theme already installed and up to date.' );
		}

		// Finally, activate theme if requested.
		if ( $result && isset( $assoc_args['activate'] ) ) {
			WP_CLI::line( "Activating '$slug'..." );
			$this->activate( array( $slug ) );
		}
	}

	protected function get_item_list() {
		return wp_list_pluck( get_themes(), 'Stylesheet' );
	}

	/**
	 * Delete a theme
	 *
	 * @param array $args
	 */
	function delete( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$r = delete_theme( $name );

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r );
		}
	}

	protected function parse_name( $args, $subcommand ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp theme $subcommand <theme-name>" );
			exit;
		}

		$name = $args[0];

		$stylesheet = $this->get_stylesheet_path( $name );

		if ( !is_readable( $stylesheet ) ) {
			WP_CLI::error( "The theme '$name' could not be found." );
			exit;
		}

		return array( $stylesheet, $name );
	}

	protected function get_stylesheet_path( $theme ) {
		return WP_CONTENT_DIR . '/themes/' . $theme . '/style.css';
	}
}
