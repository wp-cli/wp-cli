<?php

/**
 * Manage user custom fields.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'user';
}

WP_CLI::add_command( 'user-meta', 'User_Meta_Command' );

