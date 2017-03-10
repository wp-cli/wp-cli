<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress post based on one of its attributes.
 */
class Post extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "Could not find the post with ID %d.";

	/**
	 * Get a post object by ID
	 * 
	 * @param int $arg
	 * @return WP_Post|false
	 */
	public function get( $arg ) {
		return get_post( $arg );
	}
}

