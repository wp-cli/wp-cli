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
	 * : Limit the output to specific row fields. Defaults to meta_key,meta_value.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		list( $object_id ) = $args;

		$keys = ! empty( $assoc_args['keys'] ) ? explode( ',', $assoc_args['keys'] ) : false;

		$values = $this->get_metadata( $object_id, $keys );

		foreach( $values as &$value ) {

			if ( ( empty( $assoc_args['format'] ) || in_array( $assoc_args['format'], array( 'table', 'csv' ) ) ) && ( is_object( $value->meta_value ) || is_array( $value->meta_value ) ) ) {
				$value->meta_value = json_encode( $value->meta_value );
			}

		}

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		} else {
			$fields = array( 'meta_key', 'meta_value' );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields, $this->meta_type );
		$formatter->display_items( $values );

	}

	/**
	 * Get meta field value.
	 *
	 * @synopsis <id> <key> [--format=<format>]
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

		$success = \update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			\WP_CLI::success( "Updated custom field." );
		} else {
			\WP_CLI::error( "Failed to update custom field." );
		}
	}

	/**
	 * Get the fields for this object's meta
	 *
	 * @return array
	 */
	private function get_fields() {

		$fields = array();
		if ( 'user' === $this->meta_type ) {
			$fields[] = 'umeta_id';
		} else {
			$fields[] = 'meta_id';
		}
		$fields[] = "{$this->meta_type}_id";
		$fields[] = 'meta_key';
		$fields[] = 'meta_value';

		return $fields;
	}

	/**
	 * Non-lossy getter for metadata.
	 * get_metadata loses track of the meta_id
	 *
	 * @param int $object_id
	 * @return array
	 */
	private function get_metadata( $object_id, $keys = false ) {
		global $wpdb;

		$fields = implode( ', ', $this->get_fields() );
		$table = "{$this->meta_type}meta";
		$query = $wpdb->prepare( "SELECT {$fields} FROM {$wpdb->$table} WHERE {$this->meta_type}_id = %d", $object_id );
		if ( $keys ) {
			$query .= " AND meta_key IN ( '" . implode( "','", $keys ) . "')";
		}
		$results = $wpdb->get_results( $query );

		$results = array_map( function( $row ) {
			$row->meta_value = maybe_unserialize( $row->meta_value );
			return $row;
		}, $results );
		return $results;
	}
}

