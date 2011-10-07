<?php

// Add the command to the wp-cli, only if the plugin is loaded
if( class_exists( 'GoogleSitemapGeneratorLoader' ) ) {
	WP_CLI::addCommand( 'google-sitemap', 'GoogleSitemapGeneratorCommand' );
}

/**
 * Manage the Google XML Sitemap plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 * @author Andreas Creten
 */
class GoogleSitemapGeneratorCommand extends WP_CLI_Command {

	/**
	 * Re-generate the sitemap
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function rebuild( $args = array(), $vars = array() ) {
		do_action( 'sm_rebuild' );
	}

	/**
	 * Help function for this command
	 *
	 * @param array $args
	 * @return void
	 */
	public function help($args = array()) {
		// Shot the command description
		WP_CLI::line( 'Generate Google Sitemaps.' );
		WP_CLI::line();

		// Show the list of sub-commands for this command
		WP_CLI::line('Example usage:');
		WP_CLI::line('    wp google-sitemap rebuild');
		WP_CLI::line();
		WP_CLI::line('%9--- DETAILS ---%n');
		WP_CLI::line();
		WP_CLI::line('Rebuild the Google sitemaps:');
		WP_CLI::line('    wp google-sitemap rebuild');
	}
}