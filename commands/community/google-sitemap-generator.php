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
		// Show the command description
	    WP_CLI::out( <<<EOB
usage: wp google-sitemap [rebuild]

Avaliable sub-commands:
    rebuild       rebuild Google sitemap
EOB
        );
	}
}