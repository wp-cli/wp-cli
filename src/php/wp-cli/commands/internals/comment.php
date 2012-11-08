<?php

WP_CLI::add_command( 'comment', 'Comment_Command' );

/**
 * Implement 'comment' command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Comment_Command extends WP_CLI_Command {

	/**
	 * Insert a comment.
	 *
	 * @synopsis --<field>=<value> [--porcelain]
	 */
	public function create( $args, $assoc_args ) {
		$post = get_post( $assoc_args['comment_post_ID'] );
		if ( !$post ) {
			WP_CLI::error( "Cannot find post $comment_post_ID" );
		}

		// We use wp_insert_comment() instead of wp_new_comment() to stay at a low level and avoid wp_die() formatted messages or notifications
		$comment_id = wp_insert_comment( $assoc_args );

		if ( !$comment_id ) {
			WP_CLI::error( "Could not create comment" );
		}

		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $comment_id );
		else
			WP_CLI::success( "Inserted comment $comment_id." );
	}

	/**
	 * Delete a comment.
	 *
	 * @synopsis <id> [--force]
	 */
	public function delete( $args, $assoc_args ) {
		list( $comment_id ) = $args;

		if ( wp_delete_comment( $comment_id, isset( $assoc_args['force'] ) ) ) {
			WP_CLI::success( "Deleted comment $comment_id." );
		} else {
			WP_CLI::error( "Failed deleting comment $comment_id" );
		}
	}

	private function call( $args, $status, $success, $failure ) {
		list( $comment_id ) = $args;

		$func = sprintf( 'wp_%s_comment', $status );

		if ( $func( $comment_id ) ) {
			WP_CLI::success( "$success comment $comment_id." );
		} else {
			WP_CLI::error( "$failure comment $comment_id" );
		}
	}

	private function set_status( $args, $status, $success ) {
		list( $comment_id ) = $args;

		$r = wp_set_comment_status( $comment_id, 'approve', true );

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r );
		} else {
			WP_CLI::success( "$success comment $comment_id" );
		}
	}

	/**
	 * Trash a comment.
	 *
	 * @synopsis <id>
	 */
	public function trash( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Trashed', 'Failed trashing' );
	}

	/**
	 * Untrash a comment.
	 *
	 * @synopsis <id>
	 */
	public function untrash( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Untrashed', 'Failed untrashing' );
	}

	/**
	 * Spam a comment.
	 *
	 * @synopsis <id>
	 */
	public function spam( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Marked as spam', 'Failed marking as spam' );
	}

	/**
	 * Unspam a comment.
	 *
	 * @synopsis <id>
	 */
	public function unspam( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Unspammed', 'Failed unspamming' );
	}

	/**
	 * Approve a comment.
	 *
	 * @synopsis <id>
	 */
	public function approve( $args, $assoc_args ) {
		$this->set_status( $args, 'approve', "Approved" );
	}

	/**
	 * Unapprove a comment.
	 *
	 * @synopsis <id>
	 */
	public function unapprove( $args, $assoc_args ) {
		$this->set_status( $args, 'hold', "Unapproved" );
	}

	/**
	 * Count comments, on whole blog or on a given post.
	 *
	 * @synopsis [<post-id>]
	 */
	public function count( $args, $assoc_args ) {
		$post_id = isset( $args[0] ) ? $args[0] : 0;

		$count = wp_count_comments( $post_id );

		// Move total_comments to the end of the object
		$total = $count->total_comments;
		unset( $count->total_comments );
		$count->total_comments = $total;

		foreach ( $count as $status => $count ) {
			WP_CLI::line( str_pad( "$status:", 17 ) . $count );
		}
	}

	/**
	 * Get status of a comment.
	 *
	 * @synopsis <id>
	 */
	public function status( $args, $assoc_args ) {
		list( $comment_id ) = $args;

		$status = wp_get_comment_status( $comment_id );

		if ( false === $status ) {
			WP_CLI::error( "Could not check status of comment $comment_id." );
		} else {
			WP_CLI::line( $status );
		}
	}

	/**
	 * Get last approved comment.
	 *
	 * @synopsis [--id] [--full]
	 */
	function last( $args = array(), $assoc_args = array() ) {
		$last = get_comments( array( 'number' => 1, 'status' => 'approve' ) );

		list( $comment ) = $last;

		if ( isset( $assoc_args['id'] ) ) {
			WP_CLI::line( $comment->comment_ID );
			exit( 1 );
		}

		WP_CLI::line( "%yLast approved comment:%n " );

		if ( isset( $assoc_args['full'] ) ) {
			$keys = array_keys( get_object_vars( $comment ) );
		} else {
			$keys = array( 'comment_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content' );
		}

		foreach ( $keys as $key ) {
			WP_CLI::line( str_pad( "$key:", 23 ) . $comment->$key );
		}
	}
}

