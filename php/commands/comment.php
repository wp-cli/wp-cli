<?php

/**
 * Manage comments.
 *
 * @package wp-cli
 */
class Comment_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'comment';
	protected $obj_id_key = 'comment_ID';

	private $fields = array(
		'comment_ID',
		'comment_post_ID',
		'comment_date',
		'comment_approved',
		'comment_author',
		'comment_author_email',
	);

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
			$post = get_post( $params['comment_post_ID'] );
			if ( !$post ) {
				return new WP_Error( 'no_post', "Can't find post $comment_post_ID." );
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
	 * [--format=<format>]
	 * : The format to use when printing the comment, acceptable values:
	 *
	 *   - **table**: Outputs all fields of the comment as a table.
	 *   - **json**: Outputs all fields in JSON format.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment get 1 --field=content
	 */
	public function get( $args, $assoc_args ) {
		$defaults = array(
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$comment_id = (int)$args[0];
		$comment = get_comment( $comment_id );
		if ( empty( $comment ) )
			WP_CLI::error( "Invalid comment ID." );

		if ( isset( $assoc_args['field'] ) ) {
			$this->show_single_field( array( $comment ), $assoc_args['field'] );
		} else {
			$this->show_multiple_fields( $comment, $assoc_args );
		}
	}

	private function show_multiple_fields( $comment, $assoc_args ) {
		switch ( $assoc_args['format'] ) {

			case 'table':
				$fields = get_object_vars( $comment );
				unset( $fields['comment_content'] );
				\WP_CLI\Utils\assoc_array_to_table( $fields );
				break;

			case 'json':
				WP_CLI::print_value( $comment, $assoc_args );
				break;

			default:
				\WP_CLI::error( "Invalid format: " . $assoc_args['format'] );
				break;
		}
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
	 * : Limit the output to specific object fields. Defaults to comment_ID,comment_post_ID,comment_date,comment_approved,comment_author,comment_author_email
	 *
	 * [--format=<format>]
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment list --field=ID
	 *
	 *     wp comment list --post_id=2
	 *
	 *     wp comment list --number=20 --comment_approved=1
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$query_args = array();
		$defaults = array(
			'format' => 'table',
			'fields' => $this->fields
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		foreach ( $assoc_args as $key => $value ) {
			if ( true === $value )
				continue;

			$query_args[ $key ] = $value;
		}

		if ( 'ids' == $assoc_args['format'] )
			$query_args['fields'] = 'ids';

		$query = new WP_Comment_Query();
		$comments = $query->query( $query_args );

		if ( 'ids' == $assoc_args['format'] ) {
			$comments = wp_list_pluck( $comments, 'comment_ID' );
		}

		if ( isset( $assoc_args['field'] ) ) {
			$this->show_single_field( $comments, $assoc_args['field'] );
		} else {
			WP_CLI\Utils\format_items( $assoc_args['format'], $comments, $assoc_args['fields'] );
		}
	}

	/**
	 * Delete a comment.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the comment to delete.
	 *
	 * [--force]
	 * : Skip the trash bin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment delete 1337 --force
	 */
	public function delete( $args, $assoc_args ) {
		parent::_delete( $args, $assoc_args, function ( $comment_id, $assoc_args ) {
			$r = wp_delete_comment( $comment_id, isset( $assoc_args['force'] ) );

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
		$comment = $this->_fetch_comment( $args );

		$r = wp_set_comment_status( $comment->comment_ID, 'approve', true );

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r );
		} else {
			WP_CLI::success( "$success comment $comment_id" );
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
	 * <post-id>
	 * : The ID of the post to count comments in.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment count
	 *     wp comment count 42
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
		if ( $this->_fetch_comment( $args ) ) {
			WP_CLI::success( "Comment with ID $args[0] exists." );
		}
	}

	/**
	 * A helper function fetching a comment object from comment_id.
	 */
	private function _fetch_comment( $args ) {
		$comment_id = (int) $args[0];
		$comment = get_comment( $comment_id );

		if ( is_null( $comment ) ) {
			WP_CLI::error( "Comment with ID $args[0] does not exist." );
		}

		return $comment;
	}
}

WP_CLI::add_command( 'comment', 'Comment_Command' );

