<?php

WP_CLI::addCommand('theme', 'ThemeCommand');

/**
 * Implement theme command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class ThemeCommand extends WP_CLI_Command {

	protected $default_subcommand = 'status';

	/**
	 * Get the status of one or all themes
	 *
	 * @param array $args
	 **/
	public function status( $args = array() ) {
		if ( empty( $args ) ) {
			$this->list_themes();
			return;
		}

		$name = $args[0];

		$details = get_theme_data( $this->get_stylesheet_path( $name ) );

		$status = $this->get_status( $details['Name'], true );

		$version = $details[ 'Version' ];

		if ( WP_CLI::get_update_status( $name, 'update_themes' ) )
			$version .= ' (%gUpdate available%n)';

		WP_CLI::line( 'Theme %9' . $name . '%n details:' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Version: ' . $version );
		WP_CLI::line( '    Author: ' . strip_tags( $details[ 'Author' ] ) );
	}

	private function list_themes() {
		// Print the header
		WP_CLI::line( 'Installed themes:' );

		foreach ( get_themes() as $theme ) {
			if ( WP_CLI::get_update_status( $theme['Stylesheet'], 'update_themes' ) ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$line .=  $this->get_status( $theme['Name'] ) . ' ' . $theme['Stylesheet'] . '%n';

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

	private function get_status( $theme_name, $long = false ) {
		if ( get_current_theme() == $theme_name ) {
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
			WP_CLI::warning( 'parent theme not found' );
			exit;
		}

		switch_theme( $parent, $child );
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

			if ( isset( $assoc_args['directory'] ) )
				$path = dirname( $path );
		}

		WP_CLI::line( $path );
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

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp theme <sub-command> [<theme-name>]
   or: wp theme path [<theme-name>] [--directory]

Available sub-commands:
   status     display status of all installed themes or of a particular theme
   activate   activate a particular theme
   path       print path to the theme's stylesheet
   delete     delete a theme
EOB
		);
	}
}
