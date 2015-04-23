<?php

/**
 * Manage comments.
 *
 * ## EXAMPLES
 *
 *     # delete all spam comments.
 *     wp comment delete $(wp comment list --status=spam --format=ids)
 *
 * @package wp-cli
 */
class Comment_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'comment';
	protected $obj_id_key = 'comment_ID';
	protected $obj_fields = array(
		'comment_ID',
		'comment_post_ID',
		'comment_date',
		'comment_approved',
		'comment_author',
		'comment_author_email',
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\Comment;
	}

	/**
	 * Insert a comment.
	 *
	 * ## OPTIONS
	 *
	 * --<field>=<value>
	 * : Associative args for the new comment. See wp_insert_comment().
	 *
	 * [--porcelain]
	 * : Output just the new comment id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment create --comment_post_ID=15 --comment_content="hello blog" --comment_author="wp-cli"
	 */
	public function create( $args, $assoc_args ) {
		parent::_create( $args, $assoc_args, function ( $params ) {
			$post_id = $params['comment_post_ID'];
			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_Error( 'no_post', "Can't find post $post_id." );
			}

			// We use wp_insert_comment() instead of wp_new_comment() to stay at a low level and
			// avoid wp_die() formatted messages or notifications
			$comment_id = wp_insert_comment( $params );

			if ( !$comment_id ) {
				return new WP_Error( 'db_error', 'Could not create comment.' );
			}

			return $comment_id;
		} );
	}

	/**
	 * Update one or more comments.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of comments to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update. See wp_update_comment().
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment update 123 --comment_author='That Guy'
	 */
	public function update( $args, $assoc_args ) {
		parent::_update( $args, $assoc_args, function ( $params ) {
			if ( !wp_update_comment( $params ) ) {
				return new WP_Error( 'Could not update comment.' );
			}

			return true;
		} );
	}

	/**
	 * Get a single comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The comment to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole comment, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment get 1 --field=content
	 */
	public function get( $args, $assoc_args ) {
		$comment_id = (int)$args[0];
		$comment = get_comment( $comment_id );
		if ( empty( $comment ) ) {
			WP_CLI::error( "Invalid comment ID." );
		}

		if ( empty( $assoc_args['fields'] ) ) {
			$comment_array = get_object_vars( $comment );
			$assoc_args['fields'] = array_keys( $comment_array );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $comment );
	}

	/**
	 * Get a list of comments.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to WP_Comment_Query.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each comment.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each comment:
	 *
	 * * comment_ID
	 * * comment_post_ID
	 * * comment_date
	 * * comment_approved
	 * * comment_author
	 * * comment_author_email
	 *
	 * These fields are optionally available:
	 *
	 * * comment_author_url
	 * * comment_author_IP
	 * * comment_date_gmt
	 * * comment_content
	 * * comment_karma
	 * * comment_agent
	 * * comment_type
	 * * comment_parent
	 * * user_id
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment list --field=ID
	 *
	 *     wp comment list --post_id=2
	 *
	 *     wp comment list --number=20 --status=approve
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		if ( 'ids' == $formatter->format )
			$assoc_args['fields'] = 'comment_ID';

		$query = new WP_Comment_Query();
		$comments = $query->query( $assoc_args );

		if ( 'ids' == $formatter->format ) {
			$comments = wp_list_pluck( $comments, 'comment_ID' );
		}

		$formatter->display_items( $comments );
	}

	/**
	 * Delete a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of comments to delete.
	 *
	 * [--force]
	 * : Skip the trash bin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment delete 1337 --force
	 *
	 *     wp comment delete 1337 2341 --force
	 */
	public function delete( $args, $assoc_args ) {
		parent::_delete( $args, $assoc_args, function ( $comment_id, $assoc_args ) {
			$r = wp_delete_comment( $comment_id, \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) );

			if ( $r ) {
				return array( 'success', "Deleted comment $comment_id." );
			} else {
				return array( 'error', "Failed deleting comment $comment_id" );
			}
		} );
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
		$comment = $this->fetcher->get_check( $args[0] );

		$r = wp_set_comment_status( $comment->comment_ID, $status, true );

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r );
		} else {
			WP_CLI::success( "$success comment $comment->comment_ID" );
		}
	}

	/**
	 * Trash a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to trash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment trash 1337
	 */
	public function trash( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Trashed', 'Failed trashing' );
	}

	/**
	 * Untrash a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to untrash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment untrash 1337
	 */
	public function untrash( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Untrashed', 'Failed untrashing' );
	}

	/**
	 * Spam a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to mark as spam.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment spam 1337
	 */
	public function spam( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Marked as spam', 'Failed marking as spam' );
	}

	/**
	 * Unspam a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to unmark as spam.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment unspam 1337
	 */
	public function unspam( $args, $assoc_args ) {
		$this->call( $args, __FUNCTION__, 'Unspammed', 'Failed unspamming' );
	}

	/**
	 * Approve a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to approve.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment approve 1337
	 */
	public function approve( $args, $assoc_args ) {
		$this->set_status( $args, 'approve', "Approved" );
	}

	/**
	 * Unapprove a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to unapprove.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment unapprove 1337
	 */
	public function unapprove( $args, $assoc_args ) {
		$this->set_status( $args, 'hold', "Unapproved" );
	}

	/**
	 * Count comments, on whole blog or on a given post.
	 *
	 * ## OPTIONS
	 *
	 * [<post-id>]
	 * : The ID of the post to count comments in.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment count
	 *     wp comment count 42
	 */
	public function count( $args, $assoc_args ) {
		$post_id = \WP_CLI\Utils\get_flag_value( $args, 0, 0 );

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
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment status 1337
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
	 * Verify whether a comment exists.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment exists 1337
	 */
	public function exists( $args ) {
		if ( $this->fetcher->get( $args[0] ) ) {
			WP_CLI::success( "Comment with ID $args[0] exists." );
		}
	}

	/**
	 * Get comment url
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of comments to get the URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment url 123
	 */
	public function url( $args ) {
		parent::_url( $args, 'get_comment_link' );
	}
}

/**
 * Manage comment custom fields.
 *
 * ## OPTIONS
 *
 * --format=json
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp comment meta set 123 description "Mary is a WordPress developer."
 */
class Comment_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'comment';

	/**
	 * Check that the comment ID exists
	 *
	 * @param int
	 */
	protected function check_object_id( $object_id ) {
		$fetcher = new \WP_CLI\Fetchers\Comment;
		$comment = $fetcher->get_check( $object_id );
		return $comment->comment_ID;
	}
}

WP_CLI::add_command( 'comment', 'Comment_Command' );
WP_CLI::add_command( 'comment meta', 'Comment_Meta_Command' );

