<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress comment based on one of its attributes.
 */
class Comment extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "Could not find the comment with ID %d.";

	/**
	 * Get a comment object by ID
	 * 
	 * @param int $arg
	 * @return object|false
	 */
	public function get( $arg ) {
		$comment_id = (int) $arg;
		$comment = get_comment( $comment_id );

		if ( is_null( $comment ) ) {
			return false;
		}

		return $comment;
	}
}

