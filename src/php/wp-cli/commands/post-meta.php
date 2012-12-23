<?php

WP_CLI::add_command( 'post-meta', 'Post_Meta_Command' );

/**
 * Manage post custom fields.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Post_Meta_Command extends WP_CLI_Command_With_Meta {
	protected $meta_type = 'post';
}

