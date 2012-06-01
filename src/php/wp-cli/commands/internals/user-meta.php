<?php

WP_CLI::add_command('user-meta', 'User_Meta_Command');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Meta_Command extends WP_CLI_Command {
	/**
	 * Update meta field for a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function set( $args, $assoc_args ) {
		$user_id = self::get_numeric_arg_or_error($args, 0, "User ID");
		$meta_key = self::get_arg_or_error($args, 1, "meta_key");;
		$meta_value = self::get_arg_or_error($args, 2, "meta_value");;

		$success = update_user_meta( $user_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Updated user $user_id." );
		} else {
			WP_CLI::error( "Failed to set meta field" );
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

