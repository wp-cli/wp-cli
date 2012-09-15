<?php

WP_CLI::add_command( 'post', 'Post_Command' );

/**
 * Implement post command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Post_Command extends WP_CLI_Command {

	/**
	 * Create a post
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function create( $args, $assoc_args ) {
		$post_id = wp_insert_post( $assoc_args, true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( $post_id );
		}

		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $post_id );
		else
			WP_CLI::success( "Created post $post_id." );
	}

	/**
	 * Update a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function update( $args, $assoc_args ) {
		$post_id = WP_CLI::get_numeric_arg( $args, 0, "Post ID" );

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "Need some fields to update." );
		}

		$params = array_merge( $assoc_args, array( 'ID' => $post_id ) );

		if ( wp_update_post( $params ) ) {
			WP_CLI::success( "Updated post $post_id." );
		} else {
			WP_CLI::error( "Failed updating post $post_id" );
		}
	}

	/**
	 * Delete a single post, or series of posts based on arguments
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function delete( $args, $assoc_args ) {

		$defaults = array(
			'p'                     =>		null,
			'post_type'             =>		null,
			'post_author'           =>		null,
			'post_status'           =>		'any',
			'force'                 =>		false,
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		// Support for simply passing the post ID as the first argument
		if ( isset( $args[0] ) && is_numeric( $args[0] ) )
			$post_id = $args[0];
		else if ( is_numeric( $assoc_args['p'] ) )
			$post_id = $assoc_args['p'];
		else
			$post_id = false;

		if ( $post_id ) {
			$posts_to_delete = array( $post_id );
		} else {
			$query_args = array(
					'fields'            =>		'ids',
					'posts_per_page'    =>		-1,
					'post_type'         =>		$assoc_args['post_type'],
					'post_author'       =>		$assoc_args['post_author'],
					'post_status'       =>		$assoc_args['post_status'],
				);
			$maybe_posts = new WP_Query( $query_args );
			$posts_to_delete = $maybe_posts->posts;
		}

		if ( empty( $posts_to_delete ) ) {
			WP_CLI::error( "No posts to delete." );
		}

		foreach( $posts_to_delete as $post_id ) {
			if ( wp_delete_post( $post_id, (bool)$assoc_args['force'] ) ) {
				$action = ( (bool) $assoc_args['force'] ) ? 'Deleted' : 'Trashed';
				WP_CLI::success( "{$action} post $post_id." );
			} else {
				WP_CLI::error( "Failed deleting post $post_id." );
			}
		}
	}
}
