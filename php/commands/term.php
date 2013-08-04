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
	 * --fields=<fields>
	 * : Limit the output to specific object fields. Defaults to all of the term object fields.
	 *
	 * --format=<format>
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term list category --format=csv
	 *
	 *     wp term list post_tag --fields=name,slug
	 *
	 * @subcommand list
	 * @synopsis <taxonomy> [--fields=<fields>] [--format=<format>]
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
	 * --slug=<slug>
	 * : A unique slug for the new term. Defaults to sanitized version of name.
	 *
	 * --description=<description>
	 * : A description for the new term.
	 *
	 * --parent=<term-id>
	 * : A parent for the new term.
	 *
	 * --porcelain
	 * : Output just the new term id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term create Apple category --description="A type of fruit"
	 *
	 * @synopsis <term> <taxonomy> [--slug=<slug>] [--description=<description>] [--parent=<term-id>] [--porcelain]
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
	 * --name=<name>
	 * : A new name for the term.
	 *
	 * --slug=<slug>
	 * : A new slug for the term.
	 *
	 * --description=<description>
	 * : A new description for the term.
	 *
	 * --parent=<term-id>
	 * : A new parent for the term.
	 *
	 * ## EXAMPLES
	 *
	 *     wp term update 15 category --name=Apple
	 *
	 * @synopsis <term-id> <taxonomy> [--name=<name>] [--slug=<slug>] [--description=<description>] [--parent=<term-id>]
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
	 *
	 * @synopsis <term-id> <taxonomy>
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

}

WP_CLI::add_command( 'term', 'Term_Command' );

