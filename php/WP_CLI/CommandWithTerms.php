<?php

namespace WP_CLI;

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
	 * [--taxonomies=<taxonomies>]
	 * : Limit output to metadata of specific keys.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
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

		list( $object_id ) = $args;

		$this->set_obj_id( $object_id );

		$taxonomy_names = ! empty( $assoc_args['taxonomies'] ) ? explode( ',', $assoc_args['taxonomies'] ) : get_object_taxonomies( $this->get_object_type() );

		$items = wp_get_object_terms( $object_id, $taxonomy_names );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );

	}


	/**
	 * Remove a term.
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <term>
	 * : The name of the term to deleted.
	 *
	 * <taxonomy>
	 * : The name of the taxonomy type to deleted.
	 */
	public function remove( $args, $assoc_args ) {
		list( $object_id, $term, $taxonomy ) = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$terms = explode( ",", $term );

		$result = wp_remove_object_terms( $object_id, $terms, $taxonomy );

		if ( ! is_wp_error( $result ) ) {
			\WP_CLI::success( "Deleted term." );
		} else {
			\WP_CLI::error( "Failed to delete term." );
		}
	}

	/**
	 * Add a term. Appends to existed
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <term>
	 * : The name of the term to be added.
	 *
	 * <taxonomy>
	 * : The name of the taxonomy type to be added.
	 */
	public function add( $args, $assoc_args ) {
		list( $object_id, $term, $taxonomy ) = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$terms = explode( ",", $term );

		$result = wp_set_object_terms( $object_id, $terms, $taxonomy, true );

		if ( ! is_wp_error( $result ) ) {
			\WP_CLI::success( "Added term." );
		} else {
			\WP_CLI::error( "Failed to add term." );
		}
	}

	/**
	 * Set terms. Replaces existing terms
	 *
	 * <id>
	 * : The ID of the object.
	 *
	 * <term>
	 * : The name of the term to be updated.
	 *
	 * <taxonomy>
	 * : The name of the taxonomy type to be updated.
	 *
	 * @alias set
	 */
	public function set( $args, $assoc_args ) {
		list( $object_id, $term, $taxonomy ) = $args;

		$this->set_obj_id( $object_id );

		$this->taxonomy_exists( $taxonomy );

		$terms = explode( ",", $term );

		$result = wp_set_object_terms( $object_id, $terms, $taxonomy, false );

		if ( ! is_wp_error( $result ) ) {
			\WP_CLI::success( "Updated term." );
		} else {
			\WP_CLI::error( "Failed to update term." );
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
			\WP_CLI::error( 'Invalid taxonomy.' );
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
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}
}

