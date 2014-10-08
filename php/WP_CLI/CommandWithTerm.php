<?php

namespace WP_CLI;

/**
 * Base class for WP-CLI commands that deal with terms
 *
 * @package wp-cli
 */
abstract class CommandWithTerm extends \WP_CLI_Command {

	protected $meta_type;

	/**
	 * List all term associated with an object.
	 *
	 * <id>
	 * : ID for the object.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields. Defaults to term_id,name,slug,term_group,term_taxonomy_id,taxonomy,description,parent,count.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		list( $object_id ) = $args;

        $taxonomy_names = \get_object_taxonomies( $this->get_type($object_id) );

        $items = array();

        foreach($taxonomy_names as $taxonomy_name){
            $term_list = \wp_get_object_terms($object_id, $taxonomy_name);
            foreach($term_list as $term){
                $items[] = $term;
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
	 * Get terms values.
	 *
	 * @synopsis <id> <taxonomy> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {
		list( $object_id, $taxonomy ) = $args;

		$value = \wp_get_object_terms( $object_id, $taxonomy );

		if ( '' === $value )
			die(1);

		\WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Delete a term.
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
	public function delete( $args, $assoc_args ) {
		list( $object_id, $term, $taxonomy ) = $args;


		$success = \wp_remove_object_terms( $object_id, $term, $taxonomy );

		if ( $success ) {
			\WP_CLI::success( "Deleted term." );
		} else {
			\WP_CLI::error( "Failed to delete term." );
		}
	}

    /**
     * Add a term.
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

		$success = \wp_set_object_terms( $object_id, $term, $taxonomy, false );

		if ( $success ) {
			\WP_CLI::success( "Added term." );
		} else {
			\WP_CLI::error( "Failed to add term." );
		}
	}

    /**
     * Update a term.
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
	public function update( $args, $assoc_args ) {
        list( $object_id, $term, $taxonomy ) = $args;

        $success = \wp_set_object_terms( $object_id, $term, $taxonomy, true );

		if ( $success ) {
			\WP_CLI::success( "Updated term." );
		} else {
			\WP_CLI::error( "Failed to update term." );
		}
	}

    public function get_type($object_id){
        return $this->meta_type;
    }

	/**
	 * Get the fields for this object's meta
	 *
	 * @return array
	 */
	private function get_fields() {
		return array(
            "term_id",
            "name",
            "slug",
            "term_group",
            "term_taxonomy_id",
            "taxonomy",
            "description",
            "parent",
            "count"
		);
	}

}

