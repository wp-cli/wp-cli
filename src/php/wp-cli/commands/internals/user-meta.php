<?php

WP_CLI::add_command('user-meta', 'User_Meta_Command');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Meta_Command extends WP_CLI_Command {

	protected $aliases = array(
		'set' => 'update'
	);

	/**
	 * Get meta field value for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function get( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");

		$value = get_user_meta( $user_id, $meta_key, true );

		if ( '' === $value )
			die(1);

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Get meta field value for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function delete( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");

		$success = delete_user_meta( $user_id, $meta_key );

		if ( $success ) {
			WP_CLI::success( "Deleted custom field." );
		} else {
			WP_CLI::error( "Failed to delete custom field." );
		}
	}

	/**
	 * Update meta field for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function add( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");
		$meta_value = self::get_arg_or_error($args, 2, "meta_value");

		$success = add_user_meta( $user_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Added custom field." );
		} else {
			WP_CLI::error( "Failed to add custom field." );
		}
	}

	/**
	 * Update meta field for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function update( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");
		$meta_value = self::get_arg_or_error($args, 2, "meta_value");

		$success = update_user_meta( $user_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Updated custom field." );
		} else {
			WP_CLI::error( "Failed to update custom field." );
		}
	}

	private function get_numeric_arg_or_error( $args, $index, $name ) {
		$value = self::get_arg_or_error( $args, $index, $name );
		if ( ! is_numeric( $value ) ) {
			self::error_see_help( "$name must be numeric" );
		}
		return $value;
	}

	private function get_arg_or_error( $args, $index, $name ) {
		if ( ! isset( $args[$index] ) ) {
			self::error_see_help( "$name required" );
		}
		return $args[$index];
	}

	private function error_see_help( $message ) {
		WP_CLI::error( "$message (see 'wp user-meta help').");
	}
}

