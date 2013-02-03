<?php
/**
 * Manage terms.
 *
 * @package wp-cli
 */
class Term_Command extends WP_CLI_Command {

	/**
	 * List terms in a taxonomy.
	 *
	 * @subcommand list
	 * @synopsis <taxonomy> [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		list( $taxonomy ) = $args;

		$defaults = array(
				'format'           => 'table',
				'hide_empty'       => false,
			);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$terms = get_terms( array( $taxonomy ), $assoc_args );

		$fields = array(
				'term_id',
				'term_taxonomy_id',
				'name',
				'slug',
				'description',
				'parent',
				'count',
			);

		WP_CLI\Utils\format_items( $assoc_args['format'], $fields, $terms );
	}

	/**
	 * Create a term.
	 *
	 * @synopsis <term> <taxonomy> [--slug=<slug>] [--description=<description>]
	 */
	public function create( $args, $assoc_args ) {

		list( $term, $taxonomy ) = $args;

		$defaults = array(
				'slug'            => sanitize_title( $term ),
				'description'     => '',
			);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$ret = wp_insert_term( $term, $taxonomy, $assoc_args );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( "Term created." );
	}

	/**
	 * Update a term.
	 * 
	 * @synopsis <term-id> <taxonomy> [--name=<name>] [--slug=<slug>] [--description=<description>] [--parent=<parent>]
	 */
	public function update( $args, $assoc_args ) {

		list( $term_id, $taxonomy ) = $args;

		$defaults = array(
				'name'              => null,
				'slug'              => null,
				'description'       => null,
				'parent'            => null,
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
	 * @synopsis <term-id> <taxonomy>
	 */
	public function delete( $args ) {

		list( $term_id, $taxonomy ) = $args;

		$ret = wp_delete_term( $term_id, $taxonomy );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else if ( $ret )
			WP_CLI::success( "Term deleted." );
		else
			WP_CLI::error( "Error deleting term." );
	}

}

WP_CLI::add_command( 'term', 'Term_Command' );