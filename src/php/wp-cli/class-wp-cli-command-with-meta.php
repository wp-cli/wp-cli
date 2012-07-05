<?php

/**
 * Base class for WP-CLI commands that deal with metadata
 *
 * @package wp-cli
 */
abstract class WP_CLI_Command_With_Meta extends WP_CLI_Command {

	protected $meta_type;

	protected $aliases = array(
		'set' => 'update'
	);

	/**
	 * Get meta field value
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function get( $args, $assoc_args ) {
		$object_id = WP_CLI::get_numeric_arg( $args, 0, "$this->meta_type ID" );
		$meta_key = self::get_arg_or_error( $args, 1, "meta_key" );

		$value = get_metadata( $this->meta_type, $object_id, $meta_key, true );

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
		$object_id = WP_CLI::get_numeric_arg( $args, 0, "$this->meta_type ID" );
		$meta_key = self::get_arg_or_error( $args, 1, "meta_key" );

		$success = delete_metadata( $this->meta_type, $object_id, $meta_key );

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
		$object_id = WP_CLI::get_numeric_arg( $args, 0, "$this->meta_type ID" );
		$meta_key = self::get_arg_or_error( $args, 1, "meta_key" );
		$meta_value = self::get_arg_or_error( $args, 2, "meta_value" );

		$success = add_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

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
		$object_id = WP_CLI::get_numeric_arg( $args, 0, "$this->meta_type ID" );
		$meta_key = self::get_arg_or_error( $args, 1, "meta_key" );
		$meta_value = self::get_arg_or_error( $args, 2, "meta_value" );

		$success = update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Updated custom field." );
		} else {
			WP_CLI::error( "Failed to update custom field." );
		}
	}

	protected function get_arg_or_error( $args, $index, $name ) {
		if ( ! isset( $args[$index] ) ) {
			WP_CLI::error( "$name required" );
		}

		return $args[$index];
	}
}

