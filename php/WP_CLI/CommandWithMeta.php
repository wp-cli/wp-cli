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
	 * List all metadata associated with an object.
	 *
	 * <id>
	 * : ID for the object.
	 *
	 * [--keys=<keys>]
	 * : Limit output to metadata of specific keys.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields. Defaults to id,meta_key,meta_value.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		list( $object_id ) = $args;

		$keys = ! empty( $assoc_args['keys'] ) ? explode( ',', $assoc_args['keys'] ) : array();

		$object_id = $this->check_object_id( $object_id );

		$metadata = get_metadata( $this->meta_type, $object_id );
		if ( ! $metadata ) {
			$metadata = array();
		}

		$items = array();
		foreach( $metadata as $key => $values ) {

			// Skip if not requested
			if ( ! empty( $keys ) && ! in_array( $key, $keys ) ) {
				continue;
			}

			foreach( $values as $item_value ) {

				$item_value = maybe_unserialize( $item_value );

				$items[] = (object) array(
					"{$this->meta_type}_id" => $object_id,
					'meta_key'              => $key,
					'meta_value'            => $item_value,
					);

			}

		}

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		} else {
			$fields = $this->get_fields();
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields, $this->meta_type );
		$formatter->display_items( $items );

	}

	/**
	 * Get meta field value.
	 *
	 * @synopsis <id> <key> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$object_id = $this->check_object_id( $object_id );

		$value = \get_metadata( $this->meta_type, $object_id, $meta_key, true );

		if ( '' === $value )
			die(1);

		\WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Delete a meta field.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <key>
	 * : The name of the meta field to create.
	 *
	 * [<value>]
	 * : The value to delete. If omitted, all rows with key will deleted.
	 */
	public function delete( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = ! empty( $args[2] ) ? $args[2] : '';

		$object_id = $this->check_object_id( $object_id );

		$success = \delete_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			\WP_CLI::success( "Deleted custom field." );
		} else {
			\WP_CLI::error( "Failed to delete custom field." );
		}
	}

	/**
	 * Add a meta field.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <key>
	 * : The name of the meta field to create.
	 *
	 * [<value>]
	 * : The value of the meta field. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 */
	public function add( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = \WP_CLI::get_value_from_arg_or_stdin( $args, 2 );
		$meta_value = \WP_CLI::read_value( $meta_value, $assoc_args );

		$object_id = $this->check_object_id( $object_id );

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
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <key>
	 * : The name of the meta field to update.
	 *
	 * [<value>]
	 * : The new value. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * @alias set
	 */
	public function update( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = \WP_CLI::get_value_from_arg_or_stdin( $args, 2 );
		$meta_value = \WP_CLI::read_value( $meta_value, $assoc_args );

		$object_id = $this->check_object_id( $object_id );

		$meta_value = sanitize_meta( $meta_key, $meta_value, $this->meta_type );
		$old_value = sanitize_meta( $meta_key, get_metadata( $this->meta_type, $object_id, $meta_key, true ), $this->meta_type );

		if ( $meta_value === $old_value ) {
			\WP_CLI::success( "Value passed for custom field '$meta_key' is unchanged." );
		} else {
			$success = \update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

			if ( $success ) {
				\WP_CLI::success( "Updated custom field '$meta_key'." );
			} else {
				\WP_CLI::error( "Failed to update custom field '$meta_key'." );
			}

		}

	}

	/**
	 * Get the fields for this object's meta
	 *
	 * @return array
	 */
	private function get_fields() {
		return array(
			"{$this->meta_type}_id",
			'meta_key',
			'meta_value',
		);
	}

	/**
	 * Check that the object ID exists
	 *
	 * @param int
	 */
	protected function check_object_id( $object_id ) {
		// Needs to be set in subclass
		return $object_id;
	}

}
