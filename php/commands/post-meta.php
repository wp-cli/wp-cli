<?php

/**
 * Manage post custom fields.
 *
 * ## OPTIONS
 *
 * [--format=json]
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp post-meta set 123 _wp_page_template about.php
 */
class Post_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'post';
}

WP_CLI::add_command( 'post-meta', 'Post_Meta_Command' );

