<?php

namespace WP_CLI;

class FetcherPost extends Fetcher {

	protected $msg = "Could not find the post with ID %d.";

	public function get( $arg ) {
		return get_post( $arg );
	}
}

