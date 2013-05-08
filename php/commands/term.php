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

