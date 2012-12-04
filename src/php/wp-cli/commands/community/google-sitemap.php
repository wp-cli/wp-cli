<?php

if( class_exists( 'GoogleSitemapGeneratorLoader' ) ) {
	WP_CLI::add_command( 'google-sitemap', 'GoogleSitemapGenerator_Command' );
}

/**
 * Manage the Google XML Sitemap plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 */
class GoogleSitemapGenerator_Command extends WP_CLI_Command {

	/**
	 * Re-generate the sitemap
	 */
	function rebuild() {
		do_action( 'sm_rebuild' );

		WP_CLI::success( 'Sitemap rebuilt.' );
	}
}
