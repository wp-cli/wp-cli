<?php

namespace WP_CLI;

/**
 * Base class for WP-CLI commands that deal with metadata
 *
 * @package wp-cli
 */
abstract class CommandWithMeta extends \WP_CLI_Command {

	protected $meta_type;

	/**
	 * Get meta field value.
	 *
	 * @synopsis <id> <key> [--json]
	 */
	public function get( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$value = \get_metadata( $this->meta_type, $object_id, $meta_key, true );

		if ( '' === $value )
			die(1);

		\WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Delete a meta field.
	 *
	 * @synopsis <id> <key>
	 */
	public function delete( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$success = \delete_metadata( $this->meta_type, $object_id, $meta_key );

		if ( $success ) {
			\WP_CLI::success( "Deleted custom field." );
		} else {
			\WP_CLI::error( "Failed to delete custom field." );
		}
	}

	/**
	 * Add a meta field.
	 *
	 * @synopsis <id> <key> <value>
	 */
	public function add( $args, $assoc_args ) {
		list( $object_id, $meta_key, $meta_value ) = $args;

		$success = \add_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			\WP_CLI::success( "Added custom field." );
		} else {
			\WP_CLI::error( "Failed to add custom field." );
		}
	}

	/**
	 * Update a meta field.
	 *
	 * @alias set
	 * @synopsis <id> <key> <value>
	 */
	public function update( $args, $assoc_args ) {
		list( $object_id, $meta_key, $meta_value ) = $args;

		$success = \update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			\WP_CLI::success( "Updated custom field." );
		} else {
			\WP_CLI::error( "Failed to update custom field." );
		}
	}
}

