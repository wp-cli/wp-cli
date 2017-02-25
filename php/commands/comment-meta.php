<?php

/**
 * Manage comment custom fields.
 *
 * ## EXAMPLES
 *
 *     # Set comment meta
 *     $ wp comment meta set 123 description "Mary is a WordPress developer."
 *     Success: Updated custom field 'description'.
 *
 *     # Get comment meta
 *     $ wp comment meta get 123 description
 *     Mary is a WordPress developer.
 *
 *     # Update comment meta
 *     $ wp comment meta update 123 description "Mary is an awesome WordPress developer."
 *     Success: Updated custom field 'description'.
 *
 *     # Delete comment meta
 *     $ wp comment meta delete 123 description
 *     Success: Deleted custom field.
 */
class Comment_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'comment';

	/**
	 * Check that the comment ID exists
	 *
	 * @param int
	 */
	protected function check_object_id( $object_id ) {
		$fetcher = new \WP_CLI\Fetchers\Comment;
		$comment = $fetcher->get_check( $object_id );
		return $comment->comment_ID;
	}
}

WP_CLI::add_command( 'comment meta', 'Comment_Meta_Command' );
