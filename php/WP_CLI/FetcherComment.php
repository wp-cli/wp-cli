<?php

namespace WP_CLI;

class FetcherComment extends Fetcher {

	protected $msg = "Could not find the comment with ID %d.";

	public function get( $arg ) {
		$comment_id = (int) $arg;
		$comment = get_comment( $comment_id );

		if ( is_null( $comment ) ) {
			return false;
		}

		return $comment;
	}
}

