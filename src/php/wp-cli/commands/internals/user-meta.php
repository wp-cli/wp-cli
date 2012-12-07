<?php

WP_CLI::add_command( 'user-meta', 'User_Meta_Command' );

/**
 * Manage user custom fields.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Meta_Command extends WP_CLI_Command_With_Meta {
	protected $meta_type = 'user';
}

