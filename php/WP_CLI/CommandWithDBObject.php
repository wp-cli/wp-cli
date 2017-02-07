<?php

namespace WP_CLI;

/**
 * Base class for WP-CLI commands that deal with database objects.
 *
 * @package wp-cli
 */
abstract class CommandWithDBObject extends \WP_CLI_Command {

	/**
	 * @var string $object_type WordPress' expected name for the object.
	 */
	protected $obj_type;

	/**
	 * @var string $obj_id_key Key representing object's PK field in db.
	 */
	protected $obj_id_key = 'ID';

	/**
	 * @var array $obj_fields Default fields to display for each object.
	 */
	protected $obj_fields = null;

	/**
	 * Create a given database object.
	 * Exits with status.
	 *
	 * @param array $args Arguments passed to command. Generally unused.
	 * @param array $assoc_args Parameters passed to command to be passed to callback.
	 * @param string $callback Function used to create object.
	 */
	protected function _create( $args, $assoc_args, $callback ) {
		unset( $assoc_args[ $this->obj_id_key ] );

		$obj_id = $callback( $assoc_args );

		if ( is_wp_error( $obj_id ) ) {
			\WP_CLI::error( $obj_id );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) )
			\WP_CLI::line( $obj_id );
		else
			\WP_CLI::success( "Created $this->obj_type $obj_id." );
	}

	/**
	 * Update a given database object.
	 * Exits with status.
	 *
	 * @param array $args Collection of one or more object ids to update.
	 * @param array $assoc_args Fields => values to update on each object.
	 * @param string $callback Function used to update object.
	 */
	protected function _update( $args, $assoc_args, $callback ) {
		$status = 0;

		if ( empty( $assoc_args ) ) {
			\WP_CLI::error( "Need some fields to update." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'defer-term-counting' ) ) {
			wp_defer_term_counting( true );
		}

		foreach ( $args as $obj_id ) {
			$params = array_merge( $assoc_args, array( $this->obj_id_key => $obj_id ) );

			$status = $this->success_or_failure( $this->wp_error_to_resp(
				$callback( $params ),
				"Updated $this->obj_type $obj_id."
			) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'defer-term-counting' ) ) {
			wp_defer_term_counting( false );
		}

		exit( $status );
	}

	/**
	 * Transforms arguments with '__' from CSV into expected arrays
	 *
	 * @param array $assoc_args
	 * @return array
	 */
	protected static function process_csv_arguments_to_arrays( $assoc_args ) {
		foreach( $assoc_args as $k => $v ) {
			if ( false !== strpos( $k, '__' ) ) {
				$assoc_args[ $k ] = explode( ',', $v );
			}
		}
		return $assoc_args;
	}

	/**
	 * Delete a given database object.
	 * Exits with status.
	 *
	 * @param array $args Collection of one or more object ids to delete.
	 * @param array $assoc_args Any arguments needed for the callback function.
	 * @param string $callback Function used to delete object.
	 */
	protected function _delete( $args, $assoc_args, $callback ) {
		$status = 0;

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'defer-term-counting' ) ) {
			wp_defer_term_counting( true );
		}

		foreach ( $args as $obj_id ) {
			$r = $callback( $obj_id, $assoc_args );
			$status = $this->success_or_failure( $r );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'defer-term-counting' ) ) {
			wp_defer_term_counting( false );
		}

		exit( $status );
	}

	/**
	 * Format callback response to consistent format.
	 *
	 * @param WP_Error|true $r Response from CRUD callback.
	 * @param string $success_msg
	 * @return array
	 */
	protected function wp_error_to_resp( $r, $success_msg ) {
		if ( is_wp_error( $r ) )
			return array( 'error', $r->get_error_message() );
		else
			return array( 'success', $success_msg );
	}

	/**
	 * Display success or warning based on response; return proper exit code.
	 *
	 * @param array $r Formatted from a CRUD callback.
	 * @return int $status
	 */
	protected function success_or_failure( $r ) {
		list( $type, $msg ) = $r;

		if ( 'success' == $type ) {
			\WP_CLI::success( $msg );
			$status = 0;
		} else {
			\WP_CLI::warning( $msg );
			$status = 1;
		}

		return $status;
	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {

		if ( ! empty( $assoc_args['fields'] ) ) {
			if ( is_string( $assoc_args['fields'] ) ) {
				$fields = explode( ',', $assoc_args['fields'] );
			} else {
				$fields = $assoc_args['fields'];
			}
		} else {
			$fields = $this->obj_fields;
		}
		return new \WP_CLI\Formatter( $assoc_args, $fields, $this->obj_type );
	}

	/**
	 * Given a callback, display the URL for one or more objects.
	 *
	 * @param array $args One or more object references.
	 * @param string $callback Function to get URL for the object.
	 */
	protected function _url( $args, $callback ) {
		foreach ( $args as $obj_id ) {
			$object = $this->fetcher->get_check( $obj_id );
			\WP_CLI::line( $callback( $object->{$this->obj_id_key} ) );
		}
	}
}
