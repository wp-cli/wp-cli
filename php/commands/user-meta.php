<?php

/**
 * Manage user custom fields.
 *
 * ## EXAMPLES
 *
 *     # Add user meta
 *     $ wp user meta add 123 bio "Mary is an WordPress developer."
 *     Success: Added custom field.
 *
 *     # List user meta
 *     $ wp user meta list 123 --keys=nickname,description,wp_capabilities
 *     +---------+-----------------+--------------------------------+
 *     | user_id | meta_key        | meta_value                     |
 *     +---------+-----------------+--------------------------------+
 *     | 123     | nickname        | supervisor                     |
 *     | 123     | description     | Mary is a WordPress developer. |
 *     | 123     | wp_capabilities | {"administrator":true}         |
 *     +---------+-----------------+--------------------------------+
 *
 *     # Update user meta
 *     $ wp user meta update 123 bio "Mary is an awesome WordPress developer."
 *     Success: Updated custom field 'bio'.
 *
 *     # Delete user meta
 *     $ wp user meta delete 123 bio
 *     Success: Deleted custom field.
 */
class User_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'user';

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\User;
	}

	/**
	 * List all metadata associated with a user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login, user email, or user ID of the user to get metadata for.
	 *
	 * [--keys=<keys>]
	 * : Limit output to metadata of specific keys.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields. Defaults to id,meta_key,meta_value.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List user meta
	 *     $ wp user meta list 123 --keys=nickname,description,wp_capabilities
	 *     +---------+-----------------+--------------------------------+
	 *     | user_id | meta_key        | meta_value                     |
	 *     +---------+-----------------+--------------------------------+
	 *     | 123     | nickname        | supervisor                     |
	 *     | 123     | description     | Mary is a WordPress developer. |
	 *     | 123     | wp_capabilities | {"administrator":true}         |
	 *     +---------+-----------------+--------------------------------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::list_( $args, $assoc_args );
	}

	/**
	 * Get meta field value.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login, user email, or user ID of the user to get metadata for.
	 *
	 * <key>
	 * : The metadata key.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get user meta
	 *     $ wp user meta get 123 bio
	 *     Mary is an WordPress developer.
	 */
	public function get( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::get( $args, $assoc_args );
	}

	/**
	 * Delete a meta field.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login, user email, or user ID of the user to delete metadata from.
	 *
	 * <key>
	 * : The metadata key.
	 *
	 * [<value>]
	 * : The value to delete. If omitted, all rows with key will deleted.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete user meta
	 *     $ wp user meta delete 123 bio
	 *     Success: Deleted custom field.
	 */
	public function delete( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::delete( $args, $assoc_args );
	}

	/**
	 * Add a meta field.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login, user email, or user ID of the user to add metadata for.
	 *
	 * <key>
	 * : The metadata key.
	 *
	 * <value>
	 * : The new metadata value.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add user meta
	 *     $ wp user meta add 123 bio "Mary is an WordPress developer."
	 *     Success: Added custom field.
	 */
	public function add( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::add( $args, $assoc_args );
	}

	/**
	 * Update a meta field.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : The user login, user email, or user ID of the user to update metadata for.
	 *
	 * <key>
	 * : The metadata key.
	 *
	 * <value>
	 * : The new metadata value.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update user meta
	 *     $ wp user meta update 123 bio "Mary is an awesome WordPress developer."
	 *     Success: Updated custom field 'bio'.
	 *
	 * @alias set
	 */
	public function update( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::update( $args, $assoc_args );
	}

	/**
	 * Replace user_login value with user ID
	 * user meta is a special case that also supports user_login
	 *
	 * @param array
	 * @return array
	 */
	private function replace_login_with_user_id( $args ) {
		$user = $this->fetcher->get_check( $args[0] );
		$args[0] = $user->ID;
		return $args;
	}

}

WP_CLI::add_command( 'user meta', 'User_Meta_Command' );
