<?php

/**
 * Manage posts.
 *
 * @package wp-cli
 */
class Post_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'post';
	protected $obj_fields = array(
		'ID',
		'post_title',
		'post_name',
		'post_date',
		'post_status'
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\Post;
	}

	/**
	 * Create a post.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Read post content from <file>. If this value is present, the
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
	 *     wp post create --post_type=page --post_title='A future post' --post_status=future --post_date='2020-12-01 07:00:00'
	 *
	 *     wp post create ./post-content.txt --post_category=201,345 --post_title='Post from file'
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

		if ( isset( $assoc_args['post_category'] ) ) {
			$assoc_args['post_category'] = explode( ',', $assoc_args['post_category'] );
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
		$post = $this->fetcher->get_check( $args[0] );

		$r = $this->_edit( $post->post_content, "WP-CLI post {$post->ID}" );

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
	 * : Accepted values: table, json. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     # save the post content to a file
	 *     wp post get 12 --field=content > file.txt
	 */
	public function get( $args, $assoc_args ) {
		$post = $this->fetcher->get_check( $args[0] );

		$post_arr = get_object_vars( $post );
		unset( $post_arr['filter'] );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $post_arr );
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
	 * [--field=<field>]
	 * : Prints the value of a single field for each post.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each post:
	 *
	 * * ID
	 * * post_title
	 * * post_name
	 * * post_date
	 * * post_status
	 *
	 * These fields are optionally available:
	 *
	 * * post_author
	 * * post_date_gmt
	 * * post_content
	 * * post_excerpt
	 * * comment_status
	 * * ping_status
	 * * post_password
	 * * to_ping
	 * * pinged
	 * * post_modified
	 * * post_modified_gmt
	 * * post_content_filtered
	 * * post_parent
	 * * guid
	 * * menu_order
	 * * post_type
	 * * post_mime_type
	 * * comment_count
	 * * filter
	 *
	 * ## EXAMPLES
	 *
	 *     wp post list --field=ID
	 *
	 *     wp post list --post_type=post --posts_per_page=5 --format=json
	 *
	 *     wp post list --post_type=page --fields=post_title,post_status
	 *
	 *     wp post list --post_type=page,post --format=ids
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$defaults = array(
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);
		$query_args = array_merge( $defaults, $assoc_args );

		foreach ( $query_args as $key => $query_arg ) {
			if ( false !== strpos( $key, '__' )
				|| ( 'post_type' == $key && 'any' != $query_arg ) ) {
				$query_args[$key] = explode( ',', $query_arg );
			}
		}

		if ( 'ids' == $formatter->format ) {
			$query_args['fields'] = 'ids';
			$query = new WP_Query( $query_args );
			echo implode( ' ', $query->posts );
		} else {
			$query = new WP_Query( $query_args );
			$formatter->display_items( $query->posts );
		}
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
	 * [--post_content]
	 * : If set, the command reads the post_content from STDIN.
	 *
	 * [--max_depth=<number>]
	 * : For hierarchical post types, generate child posts down to a certain depth. Default: 1
	 *
	 * ## EXAMPLES
	 *
	 *     wp post generate --count=10 --post_type=page --post_date=1999-01-04
	 *     curl http://loripsum.net/api/5 | wp post generate --post_content --count=10
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
			'post_content' => '',
		);
		extract( array_merge( $defaults, $assoc_args ), EXTR_SKIP );

		// @codingStandardsIgnoreStart
		if ( !post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( "'%s' is not a registered post type.", $post_type ) );
		}

		if ( $post_author ) {
			$user_fetcher = new \WP_CLI\Fetchers\User;
			$post_author = $user_fetcher->get_check( $post_author )->ID;
		}

		if ( isset( $assoc_args['post_content'] ) ) {
			$post_content = file_get_contents( 'php://stdin' );
		}

		// Get the total number of posts
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", $post_type ) );

		$label = get_post_type_object( $post_type )->labels->singular_name;

		$hierarchical = get_post_type_object( $post_type )->hierarchical;

		$limit = $count + $total;

		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating posts', $count );

		$previous_post_id = 0;
		$current_depth = 1;
		$current_parent = 0;

		for ( $i = $total; $i < $limit; $i++ ) {

			if ( $hierarchical ) {

				if( $this->maybe_make_child() && $current_depth < $max_depth ) {

					$current_parent = $previous_post_id;
					$current_depth++;

				} else if( $this->maybe_reset_depth() ) {

					$current_depth = 1;
					$current_parent = 0;

				}
			}

			$args = array(
				'post_type' => $post_type,
				'post_title' => "$label $i",
				'post_status' => $post_status,
				'post_author' => $post_author,
				'post_parent' => $current_parent,
				'post_name' => "post-$i",
				'post_date' => $post_date,
				'post_content' => $post_content,
			);

			$post_id = wp_insert_post( $args, true );
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( $post_id );
			} else {
				$previous_post_id = $post_id;
			}

			$notify->tick();
		}
		$notify->finish();
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Get post url
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of posts get the URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post url 123
	 *
	 *     wp post url 123 324
	 */
	public function url( $args ) {
		parent::_url( $args, 'get_permalink' );
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

/**
 * Manage post custom fields.
 *
 * ## OPTIONS
 *
 * [--format=json]
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp post meta set 123 _wp_page_template about.php
 */
class Post_Meta_Command extends \WP_CLI\CommandWithMeta {
	protected $meta_type = 'post';
}

WP_CLI::add_command( 'post', 'Post_Command' );
WP_CLI::add_command( 'post meta', 'Post_Meta_Command' );

