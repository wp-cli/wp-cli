<?php

if( class_exists( 'GoogleSitemapGeneratorLoader' ) ) {
	WP_CLI::add_command( 'google-sitemap', 'GoogleSitemapGenerator_Command' );
}

/**
 * Manage the Google XML Sitemap plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 * @maintainer Andreas Creten
 */
class GoogleSitemapGenerator_Command extends WP_CLI_Command {

	/**
	 * Re-generate the sitemap
	 *
	 * @param array $args
	 * @param array $vars
	 */
	function rebuild( $args = array(), $vars = array() ) {
		do_action( 'sm_rebuild' );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp google-sitemap [rebuild]

Available sub-commands:
	rebuild    rebuild Google sitemap
EOB
	);
	}
}
