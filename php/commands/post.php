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
		'post_date',
		'post_status'
	);

	/**
	 * Create a post.
	 *
	 * ## OPTIONS
	 *
	 * [<filename>]
	 * : Read post content from <filename>. If this value is present, the
	 *     `--post_content` argument will be ignored.
	 *
	 *   Passing `-` as the filename will cause post content to
	 *   be read from STDIN.
	 *
	 * [--<field>=<value>]
	 * : Associative args for the new post. See wp_insert_post().
	 *
	 * [--edit]
	 * : Immediately open system's editor to write or edit post content.
	 *
	 *   If content is read from a file, from STDIN, or from the `--post_content`
	 *   argument, that text will be loaded into the editor.
	 *
	 * [--porcelain]
	 * : Output just the new post id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post create --post_type=page --post_status=publish --post_title='A future post' --post-status=future --post_date='2020-12-01 07:00:00'
	 *
	 *     wp post create page.txt --post_type=page --post_title='Page from file'
	 */
	public function create( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			if ( $args[0] !== '-' ) {
				$readfile = $args[0];
				if ( ! file_exists( $readfile ) || ! is_file( $readfile ) ) {
					\WP_CLI::error( "Unable to read content from $readfile." );
				}
			} else {
				$readfile = 'php://stdin';
			}

			$assoc_args['post_content'] = file_get_contents( $readfile );
		}

		if ( isset( $assoc_args['edit'] ) ) {
			$input = isset( $assoc_args['post_content'] ) ?
				$assoc_args['post_content'] : '';

			if ( $output = $this->_edit( $input, 'WP-CLI: New Post' ) )
				$assoc_args['post_content'] = $output;
			else
				$assoc_args['post_content'] = $input;
		}

		parent::_create( $args, $assoc_args, function ( $params ) {
			return wp_insert_post( $params, true );
		} );
	}

	/**
	 * Update one or more posts.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of posts to update.
	 *
	 * --<field>=<value>
	 * : One or more fields to update. See wp_update_post().
	 *
	 * ## EXAMPLES
	 *
	 *     wp post update 123 --post_name=something --post_status=draft
	 */
	public function update( $args, $assoc_args ) {
		parent::_update( $args, $assoc_args, function ( $params ) {
			return wp_update_post( $params, true );
		} );
	}

	/**
	 * Launch system editor to edit post content.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to edit.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post edit 123
	 */
	public function edit( $args, $_ ) {
		$post_id = $args[0];
		if ( !$post_id || !$post = get_post( $post_id ) )
			\WP_CLI::error( "Failed opening post $post_id to edit." );

		$r = $this->_edit( $post->post_content, "WP-CLI post $post_id" );

		if ( $r === false )
			\WP_CLI::warning( 'No change made to post content.', 'Aborted' );
		else
			$this->update( $args, array( 'post_content' => $r ) );
	}

	protected function _edit( $content, $title ) {
		$content = apply_filters( 'the_editor_content', $content );
		$output = \WP_CLI\Utils\launch_editor_for_input( $content, $title );
        return ( is_string( $output ) ) ?
            apply_filters( 'content_save_pre', $output ) : $output;
	}

	/**
	 * Get a post's content by ID.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole post, returns the value of a single field.
	 *
	 * [--format=<format>]
	 * : The format to use when printing the post, acceptable values:
	 *
	 *   - **table**: Outputs all fields of the post as a table. Note that the
	 *     post_content field is omitted so that the table is readable.
	 *
	 *   - **json**: Outputs all fields in JSON format.
	 *
	 * ## EXAMPLES
	 *
	 *     # save the post content to a file
	 *     wp post get 12 --field=content > file.txt
	 */
	public function get( $args, $assoc_args ) {
		$defaults = array(
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$post_id = $args[0];
		if ( !$post_id || !$post = get_post( $post_id ) )
			\WP_CLI::error( "Could not find the post with ID $post_id." );

		if ( isset( $assoc_args['field'] ) ) {
			$this->show_single_field( $post, $assoc_args['field'] );
		} else {
			$this->show_multiple_fields( $post, $assoc_args );
		}
	}

	private function show_multiple_fields( $post, $assoc_args ) {
		switch ( $assoc_args['format'] ) {

		case 'table':
			$fields = get_object_vars( $post );
			unset( $fields['filter'], $fields['post_content'], $fields['format_content'] );
			\WP_CLI\Utils\assoc_array_to_table( $fields );
			break;

		case 'json':
			$fields = get_object_vars( $post );
			unset( $fields['filter'] );
			WP_CLI::print_value( $fields, $assoc_args );
			break;

		default:
			\WP_CLI::error( "Invalid format: " . $assoc_args['format'] );
			break;

		}
	}

	/**
	 * Delete a post by ID.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of posts to delete.
	 *
	 * [--force]
	 * : Skip the trash bin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post delete 123 --force
	 *
	 *     wp post delete $(wp post list --post_type='page' --format=ids)
	 */
	public function delete( $args, $assoc_args ) {
		$defaults = array(
			'force' => false
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		parent::_delete( $args, $assoc_args, function ( $post_id, $assoc_args ) {
			$r = wp_delete_post( $post_id, $assoc_args['force'] );

			if ( $r ) {
				$action = $assoc_args['force'] ? 'Deleted' : 'Trashed';

				return array( 'success', "$action post $post_id." );
			} else {
				return array( 'error', "Failed deleting post $post_id." );
			}
		} );
	}

	/**
	 * Get a list of posts.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to WP_Query.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to ID,post_title,post_name,post_date,post_status.
	 *
	 * [--format=<format>]
	 * : Output list as table, CSV, JSON, or simply IDs. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post list --format=ids
	 *
	 *     wp post list --post_type=post --posts_per_page=5 --format=json
	 *
	 *     wp post list --post_type=page --fields=post_title,post_status
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$query_args = array(
			'posts_per_page'  => -1,
			'post_status'     => 'any',
		);
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

		$query = new WP_Query( $query_args );

		WP_CLI\Utils\format_items( $assoc_args['format'], $query->posts, $assoc_args['fields'] );
	}

	/**
	 * Generate some posts.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many posts to generate. Default: 100
	 *
	 * [--post_type=<type>]
	 * : The type of the generated posts. Default: 'post'
	 *
	 * [--post_status=<status>]
	 * : The status of the generated posts. Default: 'publish'
	 *
	 * [--post_author=<login>]
	 * : The author of the generated posts. Default: none
	 *
	 * [--post_date=<yyyy-mm-dd>]
	 * : The date of the generated posts. Default: current date
	 *
	 * [--max_depth=<number>]
	 * : For hierarchical post types, generate child posts down to a certain depth. Default: 1
	 *
	 * ## EXAMPLES
	 *
	 *     wp post generate --count=10 --post_type=page --post_date=1999-01-04
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

		extract( array_merge( $defaults, $assoc_args ), EXTR_SKIP );

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

		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating posts', $count );

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
