<?php

namespace WP_CLI\Fetchers;

use WP_Post;

/**
 * Fetch a WordPress post based on one of its attributes.
 */
class Post extends Base {

	/**
	 * The message to display when an item is not found.
	 *
	 * @var string
	 */
	protected $msg = 'Could not find the post with ID %d.';

	/**
	 * Get a post object by ID
	 *
	 * @param string $arg The raw CLI argument.
	 * @return WP_Post|array|false The item if found; false otherwise.
	 */
	public function get( $arg ) {
		$post = get_post( $arg );

		if ( null === $post ) {
			return false;
		}

		return $post;
	}
}

