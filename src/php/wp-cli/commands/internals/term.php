<?php

WP_CLI::add_command( 'term', 'Term_Command' );

/**
 * Implement term command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Term_Command extends WP_CLI_Command {

	/**
	 * Get terms associated with an object
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function get_object_terms( $args, $assoc_args ) {

		$default_args = array(
				'object_id'            => $args[0],
				'taxonomy'             => $args[1],
			);
		$assoc_args = array_merge( $default_args, $assoc_args );

		if ( empty( $assoc_args['object_id'] ) )
			WP_CLI::error( "Object ID required as first parameter or with --object_id=" );

		if ( empty( $assoc_args['taxonomy'] ) || ! taxonomy_exists( $assoc_args['taxonomy'] ) )
			WP_CLI::error( "Valid taxonomy required as second parameter or with --taxonomy=" );

		$terms = wp_get_object_terms( $assoc_args['object_id'], $assoc_args['taxonomy'] );
		var_dump( $terms );
	}

}