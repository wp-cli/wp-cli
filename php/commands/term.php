<?php
/**
 * Manage terms.
 *
 * @package wp-cli
 */
class Term_Command extends WP_CLI_Command {

	private $fields = array(
		'term_id',
		'term_taxonomy_id',
		'name',
		'slug',
		'description',
		'parent',
		'count',
	);

	/**
	 * List terms in a taxonomy.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : List terms of a given taxonomy.
	 *
	 * [--<field>=<value>]
	 * : Filter by one or more fields. For accepted fields, see get_terms().
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to all of the term object fields.
	 *
	 * [--format=<format>]
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term list category --format=csv
	 *
	 *     wp term list post_tag --fields=name,slug
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {

		list( $taxonomy ) = $args;

		$defaults = array(
			'fields'     => implode( ',', $this->fields ),
			'format'     => 'table',
			'hide_empty' => false,
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$fields = $assoc_args['fields'];
		unset( $assoc_args['fields'] );

		$terms = get_terms( array( $taxonomy ), $assoc_args );

		if ( 'ids' == $assoc_args['format'] )
			$terms = wp_list_pluck( $terms, 'term_id' );

		WP_CLI\Utils\format_items( $assoc_args['format'], $terms, $fields );
	}

	/**
	 * Create a term.
	 *
	 * ## OPTIONS
	 *
	 * <term>
	 * : A name for the new term.
	 *
	 * <taxonomy>
	 * : Taxonomy for the new term.
	 *
	 * [--slug=<slug>]
	 * : A unique slug for the new term. Defaults to sanitized version of name.
	 *
	 * [--description=<description>]
	 * : A description for the new term.
	 *
	 * [--parent=<term-id>]
	 * : A parent for the new term.
	 *
	 * [--porcelain]
	 * : Output just the new term id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term create Apple category --description="A type of fruit"
	 */
	public function create( $args, $assoc_args ) {

		list( $term, $taxonomy ) = $args;

		$defaults = array(
			'slug'        => sanitize_title( $term ),
			'description' => '',
			'parent'      => '',
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		if ( isset( $assoc_args['porcelain'] ) ) {
			$porcelain = true;
			unset( $assoc_args['porcelain'] );
		} else {
			$porcelain = false;
		}

		$ret = wp_insert_term( $term, $taxonomy, $assoc_args );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else {
			if ( $porcelain )
				WP_CLI::line( $ret['term_id'] );
			else
				WP_CLI::success( sprintf( "Created %s %d.", $taxonomy, $ret['term_id'] ) );
		}
	}

	/**
	 * Get a taxonomy term
	 *
	 * ## OPTIONS
	 *
	 * <term-id>
	 * : ID of the term to get
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to get
	 *
	 * [--format=<format>]
	 * : The format to use when printing the term, acceptable values:
	 *
	 *   - **table**: Outputs all fields of the term as a table.
	 *   - **json**: Outputs all fields in JSON format.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term get 1 category --format=json
	 */
	public function get( $args, $assoc_args ) {

		list( $term_id, $taxonomy ) = $args;

		$defaults = array(
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$term = get_term_by( 'id', $term_id, $taxonomy );
		if ( ! $term )
			WP_CLI::error( "Term doesn't exist." );

		switch ( $assoc_args['format'] ) {

			case 'table':
				$fields = get_object_vars( $term );
				\WP_CLI\Utils\assoc_array_to_table( $fields );
				break;

			case 'json':
				WP_CLI::print_value( $term, $assoc_args );
				break;

			default:
				\WP_CLI::error( "Invalid format: " . $assoc_args['format'] );
				break;
		}
	}

	/**
	 * Update a term.
	 *
	 * ## OPTIONS
	 *
	 * <term-id>
	 * : ID for the term to update.
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to update.
	 *
	 * [--name=<name>]
	 * : A new name for the term.
	 *
	 * [--slug=<slug>]
	 * : A new slug for the term.
	 *
	 * [--description=<description>]
	 * : A new description for the term.
	 *
	 * [--parent=<term-id>]
	 * : A new parent for the term.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term update 15 category --name=Apple
	 */
	public function update( $args, $assoc_args ) {

		list( $term_id, $taxonomy ) = $args;

		$defaults = array(
			'name'        => null,
			'slug'        => null,
			'description' => null,
			'parent'      => null,
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		foreach( $assoc_args as $key => $value ) {
			if ( is_null( $value ) )
				unset( $assoc_args[$key] );
		}

		$ret = wp_update_term( $term_id, $taxonomy, $assoc_args );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( "Term updated." );
	}

	/**
	 * Delete a term.
	 *
	 * ## OPTIONS
	 *
	 * <term-id>
	 * : ID for the term to delete.
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term delete 15 category
	 */
	public function delete( $args ) {

		list( $term_id, $taxonomy ) = $args;

		$ret = wp_delete_term( $term_id, $taxonomy );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else if ( $ret )
			WP_CLI::success( sprintf( "Deleted %s %d.", $taxonomy, $term_id ) );
		else
			WP_CLI::error( "Term doesn't exist." );
	}

	/**
	 * Generate some terms.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many terms to generate. Default: 100
	 *
	 * [--taxonomy=<taxonomy>]
	 * : The taxonomy of the generated terms. Default: 'category'
	 *
	 * [--max_depth=<number>]
	 * : Generate child terms down to a certain depth. Default: 1
	 *
	 * ## EXAMPLES
	 *
	 *     wp term generate --count=10
	 */
	public function generate( $args, $assoc_args ) {
		global $wpdb;

		$defaults = array(
			'count' => 100,
			'max_depth' => 1,
			'taxonomy' => 'category',
		);

		extract( array_merge( $defaults, $assoc_args ), EXTR_SKIP );

		if ( !taxonomy_exists( $taxonomy ) ) {
			WP_CLI::error( sprintf( "'%s' is not a registered taxonomy.", $taxonomy ) );
		}

		// Get the total number of terms
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->terms" );

		$label = get_taxonomy( $taxonomy )->labels->singular_name;
		$slug = sanitize_title_with_dashes( $label );

		$hierarchical = get_taxonomy( $taxonomy )->hierarchical;

		$limit = $count + $total;

		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating terms', $count );

		$current_depth = 1;
		$current_parent = 0;

		$args = array(
			'orderby' => 'id',
			'hierarchical' => $hierarchical,
		);

		for ( $i = $total; $i < $limit; $i++ ) {

			if ( $hierarchical ) {

				if ( $this->maybe_make_child() && $current_depth < $max_depth ) {

					$current_parent = $term['term_id'];
					$current_depth++;

				} else if ( $this->maybe_reset_depth() ) {

					$current_depth = 1;
					$current_parent = 0;

				}

			}

			$args = array(
				'parent' => $current_parent,
				'slug' => $slug . "-$i",
			);

			$term = wp_insert_term( "$label $i", $taxonomy, $args );

			$notify->tick();
		}

		delete_option( $taxonomy . '_children' );

		$notify->finish();
	}

	private function maybe_make_child() {
		// 50% chance of making child term
		return ( mt_rand(1, 2) == 1 );
	}

	private function maybe_reset_depth() {
		// 10% chance of reseting to root depth
		return ( mt_rand(1, 10) == 7 );
	}

}

WP_CLI::add_command( 'term', 'Term_Command' );

