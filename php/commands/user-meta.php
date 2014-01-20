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
 * 
 *     wp user-meta update admin first_name "George"
 */
class User_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'user';

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\User;
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
	 * : Accepted values: table, json. Default: table
	 *
	 * @synopsis <user> <key> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::get( $args, $assoc_args );
	}

	/**
	 * Delete a meta field.
	 * 
	 * <user>
	 * : The user login, user email, or user ID of the user to delete metadata from.
	 * 
	 * <key>
	 * : The metadata key.
	 *
	 * @synopsis <user> <key>
	 */
	public function delete( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::delete( $args, $assoc_args );
	}

	/**
	 * Add a meta field.
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
	 * @synopsis <user> <key> <value> [--format=<format>]
	 */
	public function add( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::add( $args, $assoc_args );
	}

	/**
	 * Update a meta field.
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
	 * @alias set
	 * @synopsis <user> <key> <value> [--format=<format>]
	 */
	public function update( $args, $assoc_args ) {
		$args = $this->replace_login_with_user_id( $args );
		parent::update( $args, $assoc_args );
	}

	/**
	 * Replace user_login value with user ID
	 * user-meta is a special case that also supports user_login
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

WP_CLI::add_command( 'user-meta', 'User_Meta_Command' );

