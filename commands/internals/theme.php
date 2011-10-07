<?php

// Add the command to the wp-cli
WP_CLI::addCommand('theme', 'ThemeCommand');

/**
 * Implement theme command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Andreas Creten
 */
class ThemeCommand extends WP_CLI_Command {

	/**
	 * Get the status of one or all themes
	 *
	 * @param array $args
	 * @return void
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
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
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
	 * @return void
	 **/
	public function activate($args = array()) {
		if ( empty( $args ) ) {
			WP_CLI::line('usage: wp theme activate <theme-name>');
			exit;
		}

		$child = array_shift( $args );

		$stylesheet = $this->get_stylesheet_path( $child );

		if ( !is_readable( $stylesheet ) ) {
			WP_CLI::warning( 'theme not found' );
			exit;
		}

		$details = get_theme_data( $stylesheet );

		$parent = $details['Template'];

		if ( empty( $parent ) ) {
			$parent = $child;
		} elseif ( !is_readable( $this->get_stylesheet_path ( $parent ) ) ) {
			WP_CLI::warning( 'parent theme not found' );
			exit;
		}

		switch_theme( $parent, $child );
	}

	protected function get_stylesheet_path( $theme ) {
		return WP_CONTENT_DIR . '/themes/' . $theme . '/style.css';
	}

	/**
	 * Help function for this command
	 *
	 * @param array $args
	 * @return void
	 */
	public function help($args = array()) {
		WP_CLI::out( <<<EOB
usage: wp theme <sub-command> [<theme-name>]

Available sub-commands:
   status     display status of all installed themes or of a particular theme
   activate   activate a particular theme

EOB
		);
	}
}
