<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Base class for WP-CLI commands that deal with terms
 *
 * @package wp-cli
 */
abstract class CommandWithTerms extends \WP_CLI_Command {

	/**
	 * @var string $object_type WordPress' expected name for the object.
	 */
	protected $obj_type;

	/**
	 * @var string $object_id WordPress' object id.
	 */
	protected $obj_id;

	/**
	 * @var array $obj_fields Default fields to display for each object.
	 */
	protected $obj_fields = array(
		"term_id",
		"name",
		"slug",
		"taxonomy"
	);

	/**
	 * List all terms associated with an object.
	 *
	 * <id>
	 * : ID for the object.
	 *
	 * <taxonomy>...
	 * : One or more taxonomies to list.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each term:
	 *
	 * * term_id
	 * * name
	 * * slug
	 * * taxonomy
	 *
	 * These fields are optionally available:
	 *
	 * * term_taxonomy_id
	 * * description
	 * * term_group
	 * * parent
	 * * count
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$defaults = array(
			'format'      => 'table',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$object_id      = array_shift( $args );
		$taxonomy_names = $args;
		$taxonomy_args = array();

		$this->set_obj_id( $object_id );

		foreach ( $taxonomy_names as $taxonomy ) {
			$this->taxonomy_exists( $taxonomy );
		}

		if ( $assoc_args['format'] == 'ids' ) {
			$taxonomy_args['fields'] = 'ids';
		}

		$items = wp_get_object_terms( $object_id, $taxonomy_names, $taxonomy_args );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );

	}


	/**
	 * Remove a term from an object.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <taxonomy>
	 * : The name of the term's taxonomy.
	 *
	 * <term>...
	 * : The name of the term or terms to be removed from the object.
	 */
	public function remove( $args, $assoc_args ) {
		$object_id      = array_shift( $args );
		$taxonomy       = array_shift( $args );
		$terms          = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$result = wp_remove_object_terms( $object_id, $terms, $taxonomy );

		if ( ! is_wp_error( $result ) ) {
			WP_CLI::success( "Deleted term." );
		} else {
			WP_CLI::error( "Failed to delete term." );
		}
	}

	/**
	 * Add a term to an object.
	 *
	 * Append the term to the existing set of terms on the object.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <taxonomy>
	 * : The name of the taxonomy type to be added.
	 *
	 * <term>...
	 * : The slug of the term or terms to be added.
	 */
	public function add( $args, $assoc_args ) {
		$object_id      = array_shift( $args );
		$taxonomy       = array_shift( $args );
		$terms          = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$result = wp_set_object_terms( $object_id, $terms, $taxonomy, true );

		if ( ! is_wp_error( $result ) ) {
			WP_CLI::success( "Added term." );
		} else {
			WP_CLI::error( "Failed to add term." );
		}
	}

	/**
	 * Set object terms.
	 *
	 * Replaces existing terms on the object.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <taxonomy>
	 * : The name of the taxonomy type to be updated.
	 *
	 * <term>...
	 * : The slug of the term or terms to be updated.
	 */
	public function set( $args, $assoc_args ) {
		$object_id      = array_shift( $args );
		$taxonomy       = array_shift( $args );
		$terms          = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$result = wp_set_object_terms( $object_id, $terms, $taxonomy, false );

		if ( ! is_wp_error( $result ) ) {
			WP_CLI::success( "Set terms." );
		} else {
			WP_CLI::error( "Failed to set terms." );
		}
	}

	/**
	 * Check if taxonomy exists
	 *
	 * @param $taxonomy
	 */
	protected function taxonomy_exists( $taxonomy ) {

		$taxonomy_names = get_object_taxonomies( $this->get_object_type() );

		if ( ! in_array( $taxonomy, $taxonomy_names ) ) {
			WP_CLI::error( "Invalid taxonomy {$taxonomy}." );
		}
	}

	/**
	 * Set obj_id Class variable
	 *
	 * @param string $obj_id
	 */
	protected function set_obj_id( $obj_id ) {
		$this->obj_id = $obj_id;
	}

	/**
	 * Get obj_id Class variable
	 *
	 * @return string
	 */
	protected function get_obj_id() {
		return $this->obj_id;
	}


	/**
	 * Get obj_type Class variable
	 *
	 * @return string $obj_type
	 */
	protected function get_object_type() {
		return $this->obj_type;
	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 *
	 * @return WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}
}

