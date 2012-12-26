<?php

/**
 * Manage post custom fields.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Post_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'post';
}

WP_CLI::add_command( 'post-meta', 'Post_Meta_Command' );

