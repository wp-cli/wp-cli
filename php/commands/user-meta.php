<?php

/**
 * Manage user custom fields.
 *
 * ## OPTIONS
 *
 * --format=json
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp user-meta set 123 description "Mary is a WordPress developer."
 */
class User_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'user';
}

WP_CLI::add_command( 'user-meta', 'User_Meta_Command' );

