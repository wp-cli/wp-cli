<?php

namespace WP_CLI;

use WP_CLI;

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
	 * <id>
	 * : The ID of the object.
	 *
	 * <key>
	 * : The name of the meta field to get.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json. Default: table
	 */
	public function get( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$object_id = $this->check_object_id( $object_id );

		$value = get_metadata( $this->meta_type, $object_id, $meta_key, true );

		if ( '' === $value )
			die(1);

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Delete a meta field.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * [<key>]
	 * : The name of the meta field to delete.
	 *
	 * [<value>]
	 * : The value to delete. If omitted, all rows with key will deleted.
	 *
	 * [--all]
	 * : Delete all meta for the object.
	 */
	public function delete( $args, $assoc_args ) {
		list( $object_id ) = $args;

		$meta_key = ! empty( $args[1] ) ? $args[1] : '';
		$meta_value = ! empty( $args[2] ) ? $args[2] : '';

		if ( empty( $meta_key ) && ! Utils\get_flag_value( $assoc_args, 'all' ) ) {
			WP_CLI::error( 'Please specify a meta key, or use the --all flag.' );
		}

		$object_id = $this->check_object_id( $object_id );

		if ( Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$errors = false;
			foreach( get_metadata( $this->meta_type, $object_id ) as $meta_key => $values ) {
				$success = delete_metadata( $this->meta_type, $object_id, $meta_key );
				if ( $success ) {
					WP_CLI::log( "Deleted '{$meta_key}' custom field." );
				} else {
					WP_CLI::warning( "Failed to delete '{$meta_key}' custom field." );
					$errors = true;
				}
			}
			if ( $errors ) {
				WP_CLI::error( 'Failed to delete all custom fields.' );
			} else {
				WP_CLI::success( 'Deleted all custom fields.' );
			}
		} else {
			$success = delete_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );
			if ( $success ) {
				WP_CLI::success( "Deleted custom field." );
			} else {
				WP_CLI::error( "Failed to delete custom field." );
			}
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
	 * : The value of the meta field. If omitted, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value.
	 * ---
	 * default: plaintext
	 * options:
	 *   - plaintext
	 *   - json
	 * ---
	 */
	public function add( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = WP_CLI::get_value_from_arg_or_stdin( $args, 2 );
		$meta_value = WP_CLI::read_value( $meta_value, $assoc_args );

		$object_id = $this->check_object_id( $object_id );

		$meta_value = wp_slash( $meta_value );
		$success = add_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			WP_CLI::success( "Added custom field." );
		} else {
			WP_CLI::error( "Failed to add custom field." );
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
	 * : The new value. If omitted, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value.
	 * ---
	 * default: plaintext
	 * options:
	 *   - plaintext
	 *   - json
	 * ---
	 *
	 * @alias set
	 */
	public function update( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = WP_CLI::get_value_from_arg_or_stdin( $args, 2 );
		$meta_value = WP_CLI::read_value( $meta_value, $assoc_args );

		$object_id = $this->check_object_id( $object_id );

		$meta_value = sanitize_meta( $meta_key, $meta_value, $this->meta_type );
		$old_value = sanitize_meta( $meta_key, get_metadata( $this->meta_type, $object_id, $meta_key, true ), $this->meta_type );

		if ( $meta_value === $old_value ) {
			WP_CLI::success( "Value passed for custom field '$meta_key' is unchanged." );
		} else {
			$meta_value = wp_slash( $meta_value );
			$success = update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

			if ( $success ) {
				WP_CLI::success( "Updated custom field '$meta_key'." );
			} else {
				WP_CLI::error( "Failed to update custom field '$meta_key'." );
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
