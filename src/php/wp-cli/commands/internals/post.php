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
	 * Update a post
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
	 * Delete a single post, or series of posts based on arguments.
	 *
	 * @synopsis [<ID>] [--post_type=<value>] [--post_author=<value>] [--post_status=<value>] [--force]
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

		$force = (bool) $assoc_args['force'];

		$action = $force ? 'Deleted' : 'Trashed';

		foreach ( $posts_to_delete as $post_id ) {
			if ( wp_delete_post( $post_id, $force ) ) {
				WP_CLI::success( "{$action} post $post_id." );
			} else {
				WP_CLI::error( "Failed deleting post $post_id." );
			}
		}
	}

	/**
	 * Generate some posts.
	 *
	 * @synopsis [--count=100] [--post_type=post] [--post_status=publish] [--post_author=<login>] [--post_date=<date>] [--max_depth=1]
	 */
	public function generate( $args, $assoc_args ) {
		global $wpdb;

		$defaults = array(
			'count' => 100,
			'max_depth' => 1,
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => false,
			'post_date' => current_time( 'mysql' ),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( !post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( "'%s' is not a registered post type.", $post_type ) );
		}

		if ( $post_author ) {
			$post_author = get_user_by( 'login', $post_author );

			if ( $post_author )
				$post_author = $post_author->ID;
		}

		// Get the total number of posts
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", $post_type ) );

		$label = get_post_type_object( $post_type )->labels->singular_name;

		$hierarchical = get_post_type_object( $post_type )->hierarchical;

		$limit = $count + $total;

		$notify = new \cli\progress\Bar( 'Generating posts', $count );

		$current_depth = 1;
		$current_parent = 0;

		for ( $i = $total; $i < $limit; $i++ ) {

			if ( $hierarchical ) {

				if( $this->maybe_make_child() && $current_depth < $max_depth ) {

					$current_parent = $post_ids[$i-1];
					$current_depth++;

				} else if( $this->maybe_reset_depth() ) {

					$current_depth = 1;
					$current_parent = 0;

				}
			}

			$args = array(
				'post_type' => $post_type,
				'post_title' =>  "$label $i",
				'post_status' => $post_status,
				'post_author' => $post_author,
				'post_parent' => $current_parent,
				'post_name' => "post-$i",
				'post_date' => $post_date,
			);

			// Not using wp_insert_post() because it's slow
			$wpdb->insert( $wpdb->posts, $args );

			$notify->tick();
		}

		$notify->finish();
	}

	private function maybe_make_child() {
		// 50% chance of making child post
		return ( mt_rand(1,2) == 1 ) ? true: false;
	}

	private function maybe_reset_depth() {
		// 10% chance of reseting to root depth
		return ( mt_rand(1,10) == 7 ) ? true : false;
	}
}
