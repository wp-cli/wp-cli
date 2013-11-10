<?php

namespace WP_CLI\Fetchers;

class Post extends Base {

	protected $msg = "Could not find the post with ID %d.";

	public function get( $arg ) {
		return get_post( $arg );
	}
}

