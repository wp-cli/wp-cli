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
	 * @synopsis <id> <key> <value> [--format=<format>]
	 */
	public function add( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = \WP_CLI::read_value( $args[2], $assoc_args );

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
	 * @synopsis <id> <key> <value> [--format=<format>]
	 */
	public function update( $args, $assoc_args ) {
		list( $object_id, $meta_key ) = $args;

		$meta_value = \WP_CLI::read_value( $args[2], $assoc_args );

		$success = \update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );

		if ( $success ) {
			\WP_CLI::success( "Updated custom field." );
		} else {
			\WP_CLI::error( "Failed to update custom field." );
		}
	}

	/**
	 * List all meta values for an object
	 * 
	 * <id>
	 * : The object ID
	 * 
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to meta_key, meta_value
	 *
	 * [--format=<format>]
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {

		list( $object_id ) = $args;

		$defaults = array(
			'fields'      => 'meta_key,meta_value',
			'format'      => 'table',
			);

		$metadata = \get_metadata( $this->meta_type, $object_id );
		if ( 'json' == $assoc_args['format'] )
			$defaults['fields'] = array_keys( $metadata );

		$assoc_args = array_merge( $defaults, $assoc_args );

		if ( 'json' == $assoc_args['format'] )
			$prepared_metadata = new \stdClass;
		else
			$prepared_metadata = array();

		foreach( $metadata as $key => $values ) {

			if ( 'json' == $assoc_args['format'] ) {

				$values = array_map( '\maybe_unserialize', $values );
				if ( count( $values ) == 1 )
					$values = $values[0];

				$prepared_metadata->$key = $values;

			} else {

				foreach( $values as $value ) {

					$prepared_single_metadata = new \stdClass;
					$prepared_single_metadata->meta_key = $key;

					$value = \maybe_unserialize( $value );
					
					$prepared_single_metadata->meta_key = $key;
					if ( is_object( $value ) || is_array( $value ) )
						$prepared_single_metadata->meta_value = \WP_CLI::prepare_print_value( $value, array( 'format' => 'json' ) );
					else
						$prepared_single_metadata->meta_value = $value;

					$prepared_metadata[] = $prepared_single_metadata;

				}

			}

		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $prepared_metadata, $assoc_args['fields'] );	
	}
}

