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
	 * Create a post.
	 *
	 * @synopsis --<field>=<value> [--porcelain]
	 */
	public function create( $_, $assoc_args ) {
		unset( $assoc_args['ID'] );

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
	 * Update a post.
	 *
	 * @synopsis <id> --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {
		list( $post_id ) = $args;

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
	 * Delete a post by ID.
	 *
	 * @synopsis <id>... [--force]
	 */
	public function delete( $post_ids, $assoc_args ) {
		$action = isset( $assoc_args['force'] ) ? 'Deleted' : 'Trashed';

		foreach ( $post_ids as $post_id ) {
			if ( wp_delete_post( $post_id, $assoc_args['force'] ) ) {
				WP_CLI::success( "{$action} post $post_id." );
			} else {
				WP_CLI::error( "Failed deleting post $post_id." );
			}
		}
	}

	/**
	 * Get a list of posts.
	 *
	 * @subcommand list
	 * @synopsis [--<field>=<value>] [--ids]
	 */
	public function _list( $_, $assoc_args ) {
		$query_args = array(
			'posts_per_page' => -1
		);

		foreach ( $assoc_args as $key => $value ) {
			if ( true === $value )
				continue;

			$query_args[ $key ] = $value;
		}

		if ( isset( $assoc_args['ids'] ) )
			$query_args['fields'] = 'ids';

		$query = new WP_Query( $query_args );

		if ( isset( $assoc_args['ids'] ) ) {
			WP_CLI::out( implode( ' ', $query->posts ) );
		} else {
			$fields = array( 'ID', 'post_title', 'post_name', 'post_date' );

			$table = new \cli\Table();

			$table->setHeaders( $fields );

			foreach ( $query->posts as $post ) {
				$line = array();

				foreach ( $fields as $field ) {
					$line[] = $post->$field;
				}

				$table->addRow( $line );
			}

			$table->display();
		}
	}

	/**
	 * Generate some posts.
	 *
	 * @synopsis [--count=100] [--post_type=<type>] [--post_status=<status>] [--post_author=<login>] [--post_date=<yyyy-mm-dd>] [--max_depth=1]
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
