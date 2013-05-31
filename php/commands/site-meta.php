<?php

/**
 * Manage site custom fields.
 *
 * @package wp-cli
 */
class Site_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'site';
}

WP_CLI::add_command( 'site-meta', 'Site_Meta_Command' );

WP_CLI::add_hook( 'before_invoke:site-meta', function () {
	if ( !is_multisite() ) {
		WP_CLI::error( 'This is not a multisite install.' );
	}
} );

