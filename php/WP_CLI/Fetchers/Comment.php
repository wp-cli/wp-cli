<?php

namespace WP_CLI\Fetchers;

use WP_Comment;

/**
 * Fetch a WordPress comment based on one of its attributes.
 */
class Comment extends Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg = 'Could not find the comment with ID %d.';

	/**
	 * Get a comment object by ID
	 *
	 * @param string $arg The raw CLI argument.
	 * @return WP_Comment|array|false The item if found; false otherwise.
	 */
	public function get( $arg ) {
		$comment_id = (int) $arg;
		$comment    = get_comment( $comment_id );

		if ( null === $comment ) {
			return false;
		}

		return $comment;
	}
}

