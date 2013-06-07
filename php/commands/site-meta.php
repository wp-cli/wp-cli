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

