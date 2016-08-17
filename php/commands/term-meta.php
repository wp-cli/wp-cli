<?php

/**
 * Manage term custom fields.
 *
 * ## EXAMPLES
 *
 *     # Set term meta
 *     $ wp term meta set 123 bio "Mary is a WordPress developer."
 *     Success: Updated custom field 'bio'.
 *
 *     # Get term meta
 *     $ wp term meta get 123 bio
 *     Mary is a WordPress developer.
 *
 *     # Update term meta
 *     $ wp term meta update 123 bio "Mary is an awesome WordPress developer."
 *     Success: Updated custom field 'bio'.
 *
 *     # Delete term meta
 *     $ wp term meta delete 123 bio
 *     Success: Deleted custom field.
 */
class Term_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'term';

	/**
	 * Check that the term ID exists
	 *
	 * @param int
	 */
	protected function check_object_id( $object_id ) {
		$term = get_term( $object_id );
		if ( ! $term ) {
			WP_CLI::error( "Could not find the term with ID {$object_id}." );
		}
		return $term->term_id;
	}

}

WP_CLI::add_command( 'term meta', 'Term_Meta_Command', array(
	'before_invoke' => function() {
		if ( \WP_CLI\Utils\wp_version_compare( '4.4', '<' ) ) {
			WP_CLI::error( "Requires WordPress 4.4 or greater." );
		}
	})
);

