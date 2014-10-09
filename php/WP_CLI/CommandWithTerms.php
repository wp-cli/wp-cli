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
     * @var array $obj_fields Default fields to display for each object.
     */
    protected $obj_fields = array(
        "term_id",
        "name",
        "slug",
        "term_group",
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
     * * term_group
     * * taxonomy
     *
     * These fields are optionally available:
     *
     * * term_taxonomy_id
     * * description
     * * parent
     * * count
     *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

        list( $object_id ) = $args;

        $taxonomy_names = ! empty( $assoc_args['taxonomies'] ) ? explode( ',', $assoc_args['taxonomies'] ) : get_object_taxonomies( $this->get_type($object_id) );

        $items = array();

        foreach($taxonomy_names as $taxonomy_name){
            $term_list = wp_get_object_terms($object_id, $taxonomy_name);
            if ( ! is_wp_error( $term_list ) ) {
                foreach($term_list as $term){
                    $items[] = $term;
                }
            }
        }

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


		$success = wp_remove_object_terms( $object_id, $term, $taxonomy );

        if ( !is_wp_error( $success ) ) {
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

		$success = wp_set_object_terms( $object_id, $term, $taxonomy, false );

        if ( !is_wp_error( $success ) ) {
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

        $success = wp_set_object_terms( $object_id, $term, $taxonomy, true );

		if ( !is_wp_error( $success ) ) {
			\WP_CLI::success( "Updated term." );
		} else {
			\WP_CLI::error( "Failed to update term." );
		}
	}

    /**
     *
     * @param  int $object_id
     * @return string $obj_type
     */
    protected function get_type($object_id){
        return $this->obj_type;
    }

	/**
     * Get Formatter object based on supplied parameters.
     *
     * @param array $assoc_args Parameters passed to command. Determines formatting.
     * @return \WP_CLI\Formatter
     */
    protected function get_formatter( &$assoc_args ) {
        return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
    }
}

