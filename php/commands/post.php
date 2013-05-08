<?php

/**
 * Manage posts.
 *
 * @package wp-cli
 */
class Post_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'post';

	private $fields = array(
		'ID',
		'post_title',
		'post_name',
		'post_date'
	);

	/**
	 * Create a post.
	 *
	 * @synopsis [<filename>] --<field>=<value> [--edit] [--porcelain]
	 */
	public function create( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {

			if ( $args[0] !== '-' ) {
				$readfile = $args[0];
				if ( ! file_exists( $readfile ) || ! is_file( $readfile ) )
					\WP_CLI::error( "Unable to read content from $readfile." );
			} else
				$readfile = 'php://stdin';

			$assoc_args['post_content'] = file_get_contents( $readfile );
		}

		if ( isset( $assoc_args['edit'] ) ) {
			$input = ( isset( $assoc_args['post_content'] ) ) ?
				$assoc_args['post_content'] : '';

			if ( $output = $this->_edit( $input, 'WP-CLI: New Post' ) )
				$assoc_args['post_content'] = $output;
			else
				$assoc_args['post_content'] = $input;
		}

		parent::create( $args, $assoc_args );
	}

	protected function _create( $params ) {
		return wp_insert_post( $params, true );
	}

	/**
	 * Update one or more posts.
	 *
	 * @synopsis <id>... --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {
		parent::update( $args, $assoc_args );
	}

	protected function _update( $params ) {
		return wp_update_post( $params, true );
	}

	/**
	 * Launch system editor to edit post content.
	 *
	 * @synopsis <id>
	 */
	public function edit( $args, $_ ) {
		$post_id = $args[0];
		if ( !$post_id || !$post = get_post( $post_id ) )
			\WP_CLI::error( "Failed opening post $post_id to edit." );

		$r = $this->_edit( $post->post_content, "WP-CLI post $post_id" );

		if ( $r === false )
			\WP_CLI::warning( 'No change made to post content.', 'Aborted' );
		else
			parent::update( $args, array( 'post_content' => $r ) );
	}

	protected function _edit( $content, $title ) {
		return \WP_CLI\Utils\launch_editor_for_input( $content, $title );
	}

	/**
	 * Get a post's content by ID.
	 *
	 * @synopsis [--format=<format>] <id>
	 */
	public function get( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'format' => 'content'
		) );
		$format = $assoc_args['format'];

		$post_id = $args[0];
		if ( !$post_id || !$post = get_post( $post_id ) )
			\WP_CLI::error( "Failed opening post $post_id to get." );

		switch ( $assoc_args['format'] ) {

		case 'content':
			echo($post->post_content);
			break;

		case 'table':
			$items = array();
			foreach ( get_object_vars( $post ) as $field => $value ) {
				if ( 'post_content' === $field )
					continue;

				if ( !is_string($value) ) {
					$value = json_encode($value);
				}

				$item = new \stdClass;
				$item->Field = $field;
				$item->Value = $value;
				$items[] = $item;
			}

			\WP_CLI\Utils\format_items( $format, $items, array( 'Field', 'Value' ) );
			break;

		case 'json':
			echo( json_encode( $post ) );
			echo( "\n" );
			break;

		default:
			\WP_CLI::error( "Invalid value for format: " . $format );
			break;

		}
	}

	/**
	 * Delete a post by ID.
	 *
	 * @synopsis <id>... [--force]
	 */
	public function delete( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'force' => false
		) );

		parent::delete( $args, $assoc_args );
	}

	protected function _delete( $post_id, $assoc_args ) {
		$r = wp_delete_post( $post_id, $assoc_args['force'] );

		if ( $r ) {
			$action = $assoc_args['force'] ? 'Deleted' : 'Trashed';

			return array( 'success', "$action post $post_id." );
		} else {
			return array( 'error', "Failed deleting post $post_id." );
		}
	}

	/**
	 * Get a list of posts.
	 *
	 * @subcommand list
	 * @synopsis [--<field>=<value>] [--fields=<fields>] [--format=<format>]
	 */
	public function _list( $_, $assoc_args ) {
		$query_args = array(
			'posts_per_page'  => -1,
			'post_status'     => 'any',
		);

		$values = array(
			'format' => 'table',
			'fields' => $this->fields
		);

		foreach ( $values as $key => &$value ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$value = $assoc_args[ $key ];
				unset( $assoc_args[ $key ] );
			}
		}
		unset( $value );

		foreach ( $assoc_args as $key => $value ) {
			if ( true === $value )
				continue;

			$query_args[ $key ] = $value;
		}

		if ( 'ids' == $values['format'] )
			$query_args['fields'] = 'ids';

		$query = new WP_Query( $query_args );

		WP_CLI\Utils\format_items( $values['format'], $query->posts, $values['fields'] );
	}

	/**
	 * Generate some posts.
	 *
	 * @synopsis [--count=<number>] [--post_type=<type>] [--post_status=<status>] [--post_author=<login>] [--post_date=<yyyy-mm-dd>] [--max_depth=<number>]
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

			wp_insert_post( $args, true );

			$notify->tick();
		}

		$notify->finish();
	}

	private function maybe_make_child() {
		// 50% chance of making child post
		return ( mt_rand(1, 2) == 1 );
	}

	private function maybe_reset_depth() {
		// 10% chance of reseting to root depth
		return ( mt_rand(1, 10) == 7 );
	}
}

WP_CLI::add_command( 'post', 'Post_Command' );
