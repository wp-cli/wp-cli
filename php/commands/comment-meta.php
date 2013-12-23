<?php

/**
 * Manage comment custom fields.
 *
 * ## OPTIONS
 *
 * --format=json
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp comment-meta set 123 description "Mary is a WordPress developer."
 */
class Comment_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'comment';
}

WP_CLI::add_command( 'comment-meta', 'Comment_Meta_Command' );
