<?php
/**
 * Implement 'comment' command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */

// Register the 'comment' command handler
WP_CLI::add_command( 'comment', 'Comment_Command' );

class Comment_Command extends WP_CLI_Command {

	/**
	 * Basic help message
	 */
	static function help() {
		WP_CLI::line( 'usage: wp comment [last|create|delete|trash|untrash|spam|unspam|approve|unapprove|count|status]' );
	}

	
	/**
	 * Insert a comment.
	 *
	 * Example: wp comment create --comment_post_ID=15 --comment_content="hello blog" --comment_author="wp-cli"
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function create( $args, $assoc_args ) {
		// Just one check: make sure the post actually exists
		$comment_post_ID = WP_CLI::get_numeric_arg( $args, 0, "Post ID" );
		$post = get_post( $comment_post_ID );
		if ( empty( $post->comment_status ) ) {
			WP_CLI::error( "Cannot find post $comment_post_ID" );
		}
		
		// We use wp_insert_comment() instead of wp_new_comment() to stay at a low level and avoid wp_die() formatted messages or notifications
		$comment_id = wp_insert_comment( $assoc_args );

		if ( 0 == $comment_id ) {
			WP_CLI::error( "Could not create comment" );
		}
		
		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $comment_id );
		else
			WP_CLI::success( "Inserted comment $comment_id." );
	}

	
	/**
	 * Delete a comment
	 *
	 * Example: wp comment delete 15 --force
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function delete( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );

		// Boolean $force parameter to bypass trash and really delete
		$force = ( isset( $assoc_args['force'] ) ? true : false );

		if ( wp_delete_comment( $comment_id, $force ) ) {
			WP_CLI::success( "Deleted comment $comment_id." );
		} else {
			WP_CLI::error( "Failed deleting comment $comment_id" );
		}
	}

	
	/**
	 * Trash a comment
	 *
	 * Example: wp comment trash 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function trash( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		if ( wp_trash_comment( $comment_id ) ) {
			WP_CLI::success( "Trashed comment $comment_id." );
		} else {
			WP_CLI::error( "Failed trashing comment $comment_id" );
		}		
	}

	
	/**
	 * Untrash a comment
	 *
	 * Example: wp comment untrash 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function untrash( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		if ( wp_untrash_comment( $comment_id ) ) {
			WP_CLI::success( "Untrashed comment $comment_id." );
		} else {
			WP_CLI::error( "Failed untrashing comment $comment_id" );
		}		
	}

	
	/**
	 * Spam a comment
	 *
	 * Example: wp comment spam 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function spam( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		if ( wp_spam_comment( $comment_id ) ) {
			WP_CLI::success( "Spammed comment $comment_id." );
		} else {
			WP_CLI::error( "Failed spamming comment $comment_id" );
		}		
	}

	
	/**
	 * Unspam a comment
	 *
	 * Example: wp comment unspam 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function unspam( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		if ( wp_unspam_comment( $comment_id ) ) {
			WP_CLI::success( "Unspammed comment $comment_id." );
		} else {
			WP_CLI::error( "Failed unspamming comment $comment_id" );
		}		
	}

	
	/**
	 * Approve a comment
	 *
	 * Example: wp comment approve 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function approve( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		$comment = wp_set_comment_status( $comment_id, 'approve', true ); // last parameter 'true' to return a WP_Error object if there is a failure
		
		if ( is_wp_error( $comment ) ) {
			WP_CLI::error( $comment );
		} else {
			WP_CLI::success( "Approved comment $comment_id" );
		}
	}


	/**
	 * Unapprove a comment
	 *
	 * Example: wp comment unapprove 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function unapprove( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		$comment = wp_set_comment_status( $comment_id, 'hold', true );
		
		if ( is_wp_error( $comment ) ) {
			WP_CLI::error( $comment );
		} else {
			WP_CLI::success( "Unapproved comment $comment_id" );
		}
	}


	/**
	 * Count comments, in whole blog or in a given post
	 *
	 * Example: "wp comment count 43" to count comments on post 43
	 * Example: "wp comment count" to count comments on blog
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function count( $args, $assoc_args ) {
		$post_id = ( isset( $args[0] ) && is_numeric( $args[0] ) ? $args[0] : 0 );
		
		$comments = wp_count_comments( $post_id );
		// Move total_comments to the end of the object
		$total = $comments->total_comments;
		unset( $comments->total_comments );
		$comments->total_comments = $total;

		foreach( $comments as $status => $count ) {
			WP_CLI::line( str_pad( "$status:", 17 ) . $count );
		}
	}


	/**
	 * Get status of a comment
	 *
	 * Example: wp comment status 15
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	public function status( $args, $assoc_args ) {
		$comment_id = WP_CLI::get_numeric_arg( $args, 0, "Comment ID" );
		
		$status = wp_get_comment_status( $comment_id );
		
		if( false === $status ) {
			WP_CLI::error( "Could not check status of comment $comment" );
		} else {
			WP_CLI::line( $status );
		}
	}

	
	/**
	 * Get last approved comment. Options: --porcelain, --full|verbose
	 *
	 * @param array $args}
	 * @param array $assoc_args
	 */
	function last( $args = array(), $assoc_args = array() ) {
		$last = get_comments( array( 'number' => 1, 'status' => 'approve' ) );
		extract( get_object_vars( $last[0] ) );
		// populates: comment_ID, comment_post_ID, comment_author, ... See http://codex.wordpress.org/Function_Reference/get_comments#Returns

		if ( isset( $assoc_args['porcelain'] ) ) {
			WP_CLI::line( $comment_ID );
			exit( 1 );
		}

		WP_CLI::line( "%yLast approved comment :%n" );

		if( isset( $assoc_args['verbose'] ) OR isset( $assoc_args['full'] ) ) {
			$keys = array_keys( get_object_vars( $last[0] ) );
		} else {
			$keys = array( 'comment_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content' );
		}
		foreach( $keys as $key ) {
			WP_CLI::line( str_pad( "$key:", 23 ) . ${$key} );
		}
	}


}
