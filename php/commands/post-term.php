<?php

/**
 * Manage post terms.
 *
 * ## EXAMPLES
 *
 *     # Set post terms
 *     $ wp post term set 123 test category
 *     Success: Set terms.
 */
class Post_Term_Command extends \WP_CLI\CommandWithTerms {
	protected $obj_type = 'post';

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\Post;
	}

	protected function get_object_type() {
		$post = $this->fetcher->get_check( $this->get_obj_id() );

		return $post->post_type;
	}
}

WP_CLI::add_command( 'post term', 'Post_Term_Command' );
