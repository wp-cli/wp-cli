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

	public static function get_description() {
		return 'Do cool things with themes.';
	}

	/**
	 * Get the status of all themes
	 *
	 * @param array $args
	 * @return void
	 **/
	public function status($args = array()) {
		// Get the list of themes
		$themes = get_themes();

		// Print the header
		WP_CLI::line('Installed themes:');

		$theme_name = get_current_theme();

		foreach ($themes as $key => $theme) {
			if ( WP_CLI::get_update_status( $theme['Stylesheet'], 'update_themes' ) ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			if ( $theme['Name'] == $theme_name )
				$line .= '%gA';
			else
				$line .= 'I';

			$line .=  ' ' . $theme['Stylesheet'].'%n';

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

	/**
	 * Get theme details
	 *
	 * @param array $args
	 * @return void
	 **/
	public function details($args = array()) {
		// Get the info of the theme
		$details = get_theme_data(WP_CONTENT_DIR.'/themes/'.$args[0].'/style.css');

		// Get the current theme
		$theme_name = get_current_theme();

		WP_CLI::line('Theme %2'.$details['Name'].'%n details:');
		WP_CLI::line('    Active: '.((int) ($details['Name'] == $theme_name)));
		WP_CLI::line('    Version: '.$details['Version']);
		WP_CLI::line('    Author: '.strip_tags($details['Author']));
		//WP_CLI::line('    Description: '.strip_tags($details['Description']));
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
		WP_CLI::line('Example usage:');
		WP_CLI::line('    wp theme status');
		WP_CLI::line('    wp theme details <theme-name>');
		WP_CLI::line('');
		WP_CLI::line('%9--- DETAILS ---%n');
		WP_CLI::line('');
		WP_CLI::line('Get a status of the installed themes:');
		WP_CLI::line('    wp theme status');
		WP_CLI::line('');
		WP_CLI::line('Get the details for a theme:');
		WP_CLI::line('    wp theme details <theme-name>');
	}
}
