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
	 * Delete a post
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function delete( $args, $assoc_args ) {
		$post_id = WP_CLI::get_numeric_arg( $args, 0, "Post ID" );

		if ( wp_delete_post( $post_id, isset( $assoc_args['force'] ) ) ) {
			WP_CLI::success( "Deleted post $post_id." );
		} else {
			WP_CLI::error( "Failed deleting post $post_id." );
		}
	}
}
